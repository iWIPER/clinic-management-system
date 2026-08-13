<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\DriveActivityLog;
use App\Models\Patient;
use App\Models\User;
use Google_Service_Drive;
use Illuminate\Support\Facades\Log;

class GoogleDriveHealthCheckService
{
    private const STANDARD_CATEGORIES = [
        'Fotografias Clínicas',
        'Radiografias',
        'Documentação',
        'Exames',
        'Ortodontia',
        'Outros',
    ];

    public function __construct(
        private GoogleDriveService $driveService
    ) {}

    /**
     * Run a full read-only health check (does not repair structure).
     */
    public function run(Patient $patient, User $doctor): array
    {
        $clinic     = $patient->clinic;
        $checkedAt  = now();
        $auditSteps = [];

        $this->logStep($patient, 'health_check_started', 'Verificação iniciada', $auditSteps);

        $report = [
            'checked_at'  => $checkedAt->toIso8601String(),
            'checked_by'  => [
                'id'   => $doctor->id,
                'name' => $doctor->name,
            ],
            'patient_name' => trim("{$patient->nome} {$patient->sobrenome}"),
            'doctor_name'  => $doctor->name,
            'clinic_name'  => $clinic?->trade_name ?? $clinic?->name,
        ];

        $report['last_verification'] = $this->getLastVerification($patient);
        $report['connection']        = $this->checkConnection($clinic, $auditSteps);

        if (!$report['connection']['connected']) {
            $hasStaleConnection = (bool) $clinic?->storageConnection;

            $report['storage']       = $this->skippedSection('Armazenamento não verificado — Drive desconectado.');
            $report['folders']       = $this->skippedFolders();
            $report['files']         = $this->skippedFiles();
            $report['orphans']       = $this->skippedOrphans();
            $report['permissions']   = $this->skippedPermissions();
            $report['api']           = $this->skippedApi('Drive não conectado.');
            $report['health_score']  = 15;
            $report['recommendations'] = [
                $hasStaleConnection
                    ? 'A conexão com o Google Drive expirou. Clique em "Reconectar Drive" para restaurar o acesso.'
                    : 'Conecte uma conta Google Drive para habilitar o armazenamento de arquivos clínicos.',
            ];
            $report['audit_summary'] = $auditSteps;
            $this->finalizeLog($patient, $doctor, $report, $auditSteps);

            return $report;
        }

        $drive = null;
        try {
            $drive = $this->driveService->getDriveForClinic($clinic);
            $this->logStep($patient, 'health_check_drive_ok', 'Drive conectado', $auditSteps);
        } catch (\Throwable $e) {
            Log::warning('[HealthCheck] Falha ao autenticar no Drive', [
                'patient_id' => $patient->id,
                'error'      => $e->getMessage(),
            ]);
        }

        $report['api'] = $this->checkApi($patient, $clinic, $drive, $auditSteps);

        $report['storage'] = $this->checkStorage($patient, $clinic, $auditSteps);

        $report['folders'] = $this->checkFolders($patient, $doctor, $drive, $auditSteps);

        $report['files'] = $this->checkFiles($patient, $drive, $auditSteps);

        $patientFolderId = $report['folders']['patient_folder_id'] ?? null;
        $report['orphans'] = $this->checkOrphans($patient, $drive, $patientFolderId, $auditSteps);

        $report['permissions'] = $this->checkPermissions(
            $patient,
            $drive,
            $patientFolderId ?? $clinic->storageConnection?->drive_root_folder_id,
            $auditSteps
        );

        $report['health_score']    = $this->calculateHealthScore($report);
        $report['recommendations'] = $this->buildRecommendations($report);
        $report['audit_summary']   = $auditSteps;

        $this->finalizeLog($patient, $doctor, $report, $auditSteps);

        return $report;
    }

    /**
     * Build a structured report when the health check throws unexpectedly.
     */
    public function buildFailureReport(Patient $patient, User $doctor, \Throwable $e): array
    {
        Log::error('[HealthCheck] Erro inesperado', [
            'patient_id' => $patient->id,
            'error'      => $e->getMessage(),
        ]);

        $clinic = $patient->clinic;

        return [
            'checked_at'   => now()->toIso8601String(),
            'checked_by'   => ['id' => $doctor->id, 'name' => $doctor->name],
            'patient_name' => trim("{$patient->nome} {$patient->sobrenome}"),
            'doctor_name'  => $doctor->name,
            'clinic_name'  => $clinic?->trade_name ?? $clinic?->name,
            'connection'   => [
                'status'    => 'warning',
                'connected' => (bool) $clinic?->storageConnection,
                'email'     => $clinic?->storageConnection?->google_email,
                'message'   => 'Conta conectada, mas parte da verificação não pôde ser concluída.',
            ],
            'storage'       => ['status' => 'unavailable', 'message' => 'Não foi possível consultar o espaço neste momento.'],
            'folders'       => ['status' => 'unavailable', 'items' => [], 'has_issues' => false],
            'files'         => ['status' => 'unavailable', 'message' => 'Comparação de arquivos não concluída.'],
            'orphans'       => ['status' => 'unavailable', 'orphan_count' => 0, 'items' => []],
            'permissions'   => ['status' => 'unavailable', 'items' => []],
            'api'           => [
                'status' => 'warning',
                'items'  => [
                    ['key' => 'drive_api', 'label' => 'Google Drive API', 'status' => 'fail', 'message' => 'Falha parcial na comunicação com a API.'],
                ],
                'reconnect_required' => false,
            ],
            'health_score'    => 50,
            'recommendations' => [
                'Algumas etapas da verificação falharam. Revise as seções marcadas e tente novamente em instantes.',
                'Se o problema persistir, reconecte a conta Google Drive.',
            ],
            'audit_summary' => [],
            'partial_failure' => true,
        ];
    }

    private function checkConnection(?Clinic $clinic, array &$auditSteps): array
    {
        $connection = $clinic?->storageConnection;

        if (!$connection) {
            return [
                'status'    => 'error',
                'connected' => false,
                'email'     => null,
                'message'   => 'Nenhuma conta Google Drive vinculada a esta clínica.',
            ];
        }

        if ($connection->status !== 'connected') {
            return [
                'status'    => 'error',
                'connected' => false,
                'email'     => $connection->google_email,
                'message'   => 'A conexão com o Google Drive expirou. Reconecte a conta para continuar.',
            ];
        }

        return [
            'status'    => 'ok',
            'connected' => true,
            'email'     => $connection->google_email,
            'message'   => 'Conta conectada corretamente.',
        ];
    }

    private function checkStorage(Patient $patient, ?Clinic $clinic, array &$auditSteps): array
    {
        try {
            $quota = $this->driveService->getStorageQuota($clinic);

            if ($quota === null) {
                $this->logStep($patient, 'health_check_storage', 'Espaço: ilimitado (Workspace)', $auditSteps);

                return [
                    'status'          => 'unlimited',
                    'usage_bytes'     => null,
                    'limit_bytes'     => null,
                    'available_bytes' => null,
                    'percentage'      => null,
                    'message'         => 'Conta com armazenamento ilimitado (Google Workspace).',
                    'level'           => 'ok',
                ];
            }

            $pct = $quota['percentage'];
            $level = match (true) {
                $pct >= 95 => 'critical',
                $pct >= 70 => 'warning',
                default    => 'ok',
            };

            $message = match ($level) {
                'critical' => 'Crítico — Utilização acima de 95%.',
                'warning'  => 'Atenção — Utilização acima de 70%.',
                default    => 'Tudo dentro do esperado.',
            };

            $this->logStep(null, 'health_check_storage', "Espaço: {$pct}%", $auditSteps, $clinic->id);

            return array_merge($quota, [
                'status'  => $level === 'ok' ? 'ok' : $level,
                'level'   => $level,
                'message' => $message,
            ]);
        } catch (\Throwable $e) {
            return [
                'status'  => 'unavailable',
                'message' => 'Não foi possível consultar o espaço de armazenamento neste momento.',
                'level'   => 'unavailable',
            ];
        }
    }

    private function checkFolders(
        Patient $patient,
        User $doctor,
        ?Google_Service_Drive $drive,
        array &$auditSteps
    ): array {
        $items            = [];
        $hasIssues        = false;
        $patientFolderId  = null;
        $patientName      = trim("{$patient->nome} {$patient->sobrenome}");
        $clinic           = $patient->clinic;
        $connection       = $clinic->storageConnection;
        $pivot            = $doctor->clinics()->where('clinics.id', $clinic->id)->first()?->pivot;

        if (!$drive) {
            return [
                'status'            => 'unavailable',
                'items'             => [],
                'has_issues'        => false,
                'can_repair'        => false,
                'patient_folder_id' => null,
                'message'           => 'Não foi possível verificar a estrutura de pastas — falha na autenticação.',
            ];
        }

        if (!$this->driveService->structureWasPreviouslyEstablished($patient, $doctor)) {
            $items[] = [
                'key'     => 'root',
                'label'   => 'Wildental',
                'status'  => 'not_setup',
                'message' => 'Estrutura ainda não criada — será gerada no primeiro upload.',
            ];

            return [
                'status'            => 'ok',
                'items'             => $items,
                'has_issues'        => false,
                'can_repair'        => false,
                'patient_folder_id' => null,
                'message'           => 'Nenhuma estrutura anterior encontrada. Primeiro upload criará as pastas.',
            ];
        }

        // Level 1 — Wildental
        $rootId = $connection->drive_root_folder_id;
        if ($rootId && $this->driveService->folderExists($rootId, $drive)) {
            $items[] = ['key' => 'root', 'label' => 'Wildental', 'status' => 'ok', 'message' => null];
        } else {
            $hasIssues = true;
            $items[] = ['key' => 'root', 'label' => 'Wildental', 'status' => 'missing', 'message' => 'Pasta raiz não encontrada.'];
        }

        // Level 2 — Professional
        $doctorId   = $pivot?->drive_doctor_folder_id;
        $doctorLabel = $doctor->name;
        if ($hasIssues) {
            $items[] = ['key' => 'doctor', 'label' => $doctorLabel, 'status' => 'skipped', 'message' => 'Não verificado — pasta ancestral ausente.'];
        } elseif ($doctorId && $this->driveService->folderExists($doctorId, $drive)) {
            $items[] = ['key' => 'doctor', 'label' => $doctorLabel, 'status' => 'ok', 'message' => null];
        } else {
            $hasIssues = true;
            $items[] = ['key' => 'doctor', 'label' => $doctorLabel, 'status' => 'missing', 'message' => 'Pasta do profissional não encontrada.'];
        }

        // Level 3 — Patient
        $patId = $patient->drive_folder_id;
        if ($hasIssues) {
            $items[] = ['key' => 'patient', 'label' => $patientName, 'status' => 'skipped', 'message' => 'Não verificado — pasta ancestral ausente.'];
        } elseif ($patId && $this->driveService->folderExists($patId, $drive)) {
            $patientFolderId = $patId;
            $items[] = ['key' => 'patient', 'label' => $patientName, 'status' => 'ok', 'message' => null];
        } else {
            $hasIssues = true;
            $items[] = ['key' => 'patient', 'label' => $patientName, 'status' => 'missing', 'message' => 'Pasta do paciente não encontrada.'];
        }

        // Level 4 — Categories
        if ($patientFolderId) {
            foreach (self::STANDARD_CATEGORIES as $category) {
                $catId = $this->driveService->locateFolder($category, $patientFolderId, $drive);
                if ($catId) {
                    $items[] = ['key' => "cat_{$category}", 'label' => $category, 'status' => 'ok', 'message' => null];
                } else {
                    $hasIssues = true;
                    $items[] = [
                        'key'     => "cat_{$category}",
                        'label'   => $category,
                        'status'  => 'missing',
                        'message' => "Pasta {$category} não encontrada.",
                    ];
                }
            }
        }

        if ($hasIssues) {
            $this->logStep($patient, 'health_check_structure_issue', 'Estrutura inconsistente detectada', $auditSteps);
        } else {
            $this->logStep($patient, 'health_check_structure_ok', 'Estrutura OK', $auditSteps);
        }

        return [
            'status'            => $hasIssues ? 'warning' : 'ok',
            'items'             => $items,
            'has_issues'        => $hasIssues,
            'can_repair'        => $hasIssues,
            'patient_folder_id' => $patientFolderId,
            'message'           => $hasIssues
                ? 'Foi detectada inconsistência. A estrutura pode ser recriada automaticamente.'
                : 'Todas as pastas verificadas estão presentes.',
        ];
    }

    private function checkFiles(Patient $patient, ?Google_Service_Drive $drive, array &$auditSteps): array
    {
        if (!$drive) {
            return [
                'status'         => 'unavailable',
                'db_count'       => 0,
                'drive_count'    => 0,
                'missing_count'  => 0,
                'missing'        => [],
                'restored_count' => 0,
                'message'        => 'Comparação de arquivos não realizada — falha na autenticação.',
            ];
        }

        $syncResult = $this->driveService->syncPatientLibrary($patient);

        $activePhotos = $patient->photos()
            ->where('status', 'active')
            ->whereNotNull('drive_file_id')
            ->get();

        $fileIds = $activePhotos->pluck('drive_file_id')->filter()->values()->all();
        $existingOnDrive = !empty($fileIds)
            ? count($this->driveService->batchLookupFileIds($drive, $fileIds))
            : 0;

        $missingCount = $patient->photos()
            ->whereIn('status', ['removed', 'missing'])
            ->whereNotNull('drive_file_id')
            ->count();

        $dbCount = $activePhotos->count() + $missingCount;
        $driveCount = $existingOnDrive;

        $missingPhotos = $patient->photos()
            ->whereIn('status', ['removed', 'missing'])
            ->whereNotNull('drive_file_id')
            ->latest('updated_at')
            ->get()
            ->take(20);

        $missing = $missingPhotos->map(fn ($p) => [
            'id'            => $p->id,
            'name'          => $p->subcategoria ?? $p->filename,
            'filename'      => $p->filename,
            'categoria'     => $p->categoria,
            'subcategoria'  => $p->subcategoria,
            'dente'         => $p->dente,
            'taken_at'      => $p->taken_at?->toIso8601String(),
            'status'        => 'Não localizado no Google Drive',
            'drive_file_id' => $p->drive_file_id,
        ])->values()->all();

        if ($missingCount > 0) {
            $this->logStep($patient, 'health_check_files_issue', "{$missingCount} arquivo(s) ausente(s) no Drive", $auditSteps);
        } else {
            $this->logStep($patient, 'health_check_files_ok', 'Nenhuma inconsistência de arquivos', $auditSteps);
        }

        return [
            'status'          => $missingCount > 0 ? 'warning' : 'ok',
            'db_count'        => $dbCount,
            'drive_count'     => $driveCount,
            'active_count'    => $activePhotos->count(),
            'missing_count'   => $missingCount,
            'missing'         => $missing,
            'restored_count'  => $syncResult['restored'] ?? 0,
            'synced_count'    => $syncResult['checked'] ?? 0,
            'message'         => match (true) {
                $missingCount === 0 => 'Todos os arquivos ativos foram localizados no Drive.',
                $missingCount === 1 => '1 arquivo ausente no Google Drive.',
                default             => "{$missingCount} arquivos ausentes no Google Drive.",
            },
        ];
    }

    private function checkOrphans(
        Patient $patient,
        ?Google_Service_Drive $drive,
        ?string $patientFolderId,
        array &$auditSteps
    ): array {
        if (!$drive || !$patientFolderId) {
            return [
                'status'        => 'skipped',
                'drive_count'   => 0,
                'system_count'  => $patient->photos()->where('status', 'active')->count(),
                'orphan_count'  => 0,
                'items'         => [],
                'message'       => 'Verificação de órfãos não realizada — pasta do paciente indisponível.',
            ];
        }

        try {
            $driveFiles = $this->driveService->listAllPatientFiles($patientFolderId, $drive);
            $systemIds  = $patient->photos()
                ->whereNotNull('drive_file_id')
                ->pluck('drive_file_id')
                ->flip();

            $orphans = collect($driveFiles)
                ->filter(fn ($f) => !isset($systemIds[$f['id']]))
                ->filter(fn ($f) => !str_starts_with($f['name'], '.wildental-'))
                ->values()
                ->map(fn ($f) => [
                    'drive_file_id' => $f['id'],
                    'name'          => $f['name'],
                    'folder'        => $f['folder'],
                    'mime_type'     => $f['mimeType'],
                ])
                ->all();

            $orphanCount = count($orphans);

            if ($orphanCount > 0) {
                $this->logStep($patient, 'health_check_orphans', "{$orphanCount} arquivo(s) órfão(s) no Drive", $auditSteps);
            }

            return [
                'status'       => $orphanCount > 0 ? 'warning' : 'ok',
                'drive_count'  => count($driveFiles),
                'system_count' => $systemIds->count(),
                'orphan_count' => $orphanCount,
                'items'        => array_slice($orphans, 0, 20),
                'message'      => match (true) {
                    $orphanCount === 0 => 'Nenhum arquivo órfão detectado.',
                    $orphanCount === 1 => '1 arquivo no Drive não está cadastrado no sistema.',
                    default            => "{$orphanCount} arquivos no Drive não estão cadastrados no sistema.",
                },
            ];
        } catch (\Throwable $e) {
            Log::warning('[HealthCheck] Falha ao verificar órfãos', ['error' => $e->getMessage()]);

            return [
                'status'       => 'unavailable',
                'drive_count'  => 0,
                'system_count' => $patient->photos()->count(),
                'orphan_count' => 0,
                'items'        => [],
                'message'      => 'Não foi possível verificar arquivos órfãos neste momento.',
            ];
        }
    }

    private function checkPermissions(
        Patient $patient,
        ?Google_Service_Drive $drive,
        ?string $folderId,
        array &$auditSteps
    ): array {
        if (!$drive || !$folderId) {
            return [
                'status'  => 'skipped',
                'items'   => [],
                'message' => 'Permissões não testadas — pasta de destino indisponível.',
            ];
        }

        try {
            $items = $this->driveService->probeDrivePermissions($folderId, $drive);
            $failed = collect($items)->where('status', 'fail')->count();

            if ($failed === 0) {
                $this->logStep($patient, 'health_check_permissions_ok', 'Permissões validadas', $auditSteps);
            }

            return [
                'status'  => $failed > 0 ? 'warning' : 'ok',
                'items'   => $items,
                'message' => $failed > 0
                    ? 'Algumas permissões não puderam ser validadas.'
                    : 'Todas as permissões verificadas com sucesso.',
            ];
        } catch (\Throwable) {
            return [
                'status'  => 'unavailable',
                'items'   => [],
                'message' => 'Não foi possível testar permissões neste momento.',
            ];
        }
    }

    private function checkApi(Patient $patient, ?Clinic $clinic, ?Google_Service_Drive $drive, array &$auditSteps): array
    {
        $connection = $clinic?->storageConnection;
        $items      = [];
        $reconnect  = false;

        $items[] = [
            'key'     => 'drive_api',
            'label'   => 'Google Drive API',
            'status'  => $drive ? 'ok' : 'fail',
            'message' => $drive ? null : 'Não foi possível comunicar com a API.',
        ];

        $items[] = [
            'key'    => 'oauth',
            'label'  => 'OAuth',
            'status' => ($connection && $connection->refresh_token) ? 'ok' : 'fail',
            'message' => null,
        ];

        $hasRefresh = (bool) ($connection?->refresh_token);
        $items[] = [
            'key'     => 'refresh_token',
            'label'   => 'Refresh Token',
            'status'  => $hasRefresh ? 'ok' : 'fail',
            'message' => $hasRefresh ? null : 'Refresh Token expirado ou ausente.',
        ];

        $tokenValid = false;
        if ($drive && $hasRefresh) {
            try {
                $drive->about->get(['fields' => 'user']);
                $tokenValid = true;
            } catch (\Throwable) {
                $tokenValid = false;
            }
        }

        $items[] = [
            'key'     => 'access_token',
            'label'   => 'Token válido',
            'status'  => $tokenValid ? 'ok' : ($hasRefresh ? 'fail' : 'skipped'),
            'message' => $tokenValid ? null : 'Token de acesso inválido ou expirado.',
        ];

        $failed = collect($items)->where('status', 'fail')->count();
        $reconnect = collect($items)->whereIn('key', ['refresh_token', 'access_token'])->contains(fn ($i) => $i['status'] === 'fail');

        if (!$reconnect && $drive) {
            $this->logStep($patient, 'health_check_api_ok', 'API e autenticação OK', $auditSteps);
        }

        return [
            'status'             => $failed > 0 ? 'warning' : 'ok',
            'items'              => $items,
            'reconnect_required' => $reconnect,
            'message'            => $reconnect
                ? 'Reconexão com o Google Drive recomendada.'
                : 'Integração com a API funcionando corretamente.',
        ];
    }

    private function calculateHealthScore(array $report): int
    {
        $score = 100;

        if (!($report['connection']['connected'] ?? false)) {
            return 15;
        }

        if (($report['api']['status'] ?? '') === 'warning') {
            $score -= 20;
        }

        if (($report['storage']['level'] ?? '') === 'critical') {
            $score -= 15;
        } elseif (($report['storage']['level'] ?? '') === 'warning') {
            $score -= 8;
        }

        if ($report['folders']['has_issues'] ?? false) {
            $missing = collect($report['folders']['items'] ?? [])->where('status', 'missing')->count();
            $score -= min(30, $missing * 5);
        }

        $missingFiles = $report['files']['missing_count'] ?? 0;
        $score -= min(25, $missingFiles * 4);

        $orphans = $report['orphans']['orphan_count'] ?? 0;
        $score -= min(15, $orphans * 3);

        $permFails = collect($report['permissions']['items'] ?? [])->where('status', 'fail')->count();
        $score -= $permFails * 4;

        return max(0, min(100, (int) round($score)));
    }

    private function buildRecommendations(array $report): array
    {
        $tips = [];

        if (!($report['connection']['connected'] ?? false)) {
            return ['Conecte uma conta Google Drive para habilitar o armazenamento.'];
        }

        if ($report['api']['reconnect_required'] ?? false) {
            $tips[] = 'O token de autenticação precisa ser renovado. Reconecte a conta Google Drive.';
        }

        if ($report['folders']['has_issues'] ?? false) {
            $tips[] = 'Existe inconsistência na estrutura de pastas. Recomendamos recriar a estrutura.';
        }

        $missing = $report['files']['missing_count'] ?? 0;
        if ($missing > 0) {
            $tips[] = "Existe {$missing} arquivo(s) ausente(s) no Drive. Verifique a aba Removidos ou restaure pela Lixeira do Google.";
        }

        $orphans = $report['orphans']['orphan_count'] ?? 0;
        if ($orphans > 0) {
            $tips[] = "{$orphans} arquivo(s) no Drive não estão cadastrados no sistema. Revise os arquivos órfãos.";
        }

        if (($report['storage']['level'] ?? '') === 'critical') {
            $tips[] = 'O Drive está acima de 95% de uso. Recomendamos adquirir mais armazenamento.';
        } elseif (($report['storage']['level'] ?? '') === 'warning') {
            $tips[] = 'O Drive está acima de 70% de uso. Monitore o espaço disponível.';
        }

        if (empty($tips)) {
            $tips[] = 'Tudo funcionando corretamente.';
        }

        return $tips;
    }

    private function getLastVerification(Patient $patient): ?array
    {
        $log = DriveActivityLog::where('patient_id', $patient->id)
            ->where('event_type', 'health_check_completed')
            ->latest('created_at')
            ->first();

        if (!$log) {
            return null;
        }

        return [
            'at'           => $log->created_at?->toIso8601String(),
            'by'           => $log->metadata['checked_by'] ?? null,
            'health_score' => $log->metadata['health_score'] ?? null,
        ];
    }

    private function finalizeLog(Patient $patient, User $doctor, array $report, array &$auditSteps): void
    {
        $summary = $report['health_score'] >= 90
            ? 'Nenhuma inconsistência crítica encontrada.'
            : 'Verificação concluída com alertas.';

        $this->logStep($patient, 'health_check_completed', $summary, $auditSteps);

        DriveActivityLog::create([
            'clinic_id'   => $patient->clinic_id,
            'patient_id'  => $patient->id,
            'event_type'  => 'health_check_completed',
            'description' => "Relatório de integridade — Health Score {$report['health_score']}%",
            'metadata'    => [
                'checked_by'       => $doctor->name,
                'health_score'     => $report['health_score'],
                'recommendations'  => $report['recommendations'],
                'audit_summary'    => $auditSteps,
                'folders_issues'   => $report['folders']['has_issues'] ?? false,
                'missing_files'    => $report['files']['missing_count'] ?? 0,
                'orphan_files'     => $report['orphans']['orphan_count'] ?? 0,
                'storage_percent'  => $report['storage']['percentage'] ?? null,
            ],
        ]);
    }

    private function logStep(
        ?Patient $patient,
        string $eventType,
        string $description,
        array &$auditSteps
    ): void {
        $icon = match (true) {
            str_contains($eventType, 'issue') || str_contains($eventType, 'orphan') => '⚠',
            str_contains($description, 'não') || str_contains($description, 'ausente') || str_contains($description, 'inconsistente') => '⚠',
            default => '✓',
        };

        $auditSteps[] = [
            'event'       => $eventType,
            'description' => $description,
            'icon'        => $icon,
        ];
    }

    private function skippedSection(string $message): array
    {
        return ['status' => 'skipped', 'message' => $message, 'level' => 'skipped'];
    }

    private function skippedFolders(): array
    {
        return ['status' => 'skipped', 'items' => [], 'has_issues' => false, 'can_repair' => false, 'message' => 'Verificação não realizada.'];
    }

    private function skippedFiles(): array
    {
        return ['status' => 'skipped', 'db_count' => 0, 'drive_count' => 0, 'missing_count' => 0, 'missing' => [], 'message' => 'Verificação não realizada.'];
    }

    private function skippedOrphans(): array
    {
        return ['status' => 'skipped', 'orphan_count' => 0, 'items' => [], 'message' => 'Verificação não realizada.'];
    }

    private function skippedPermissions(): array
    {
        return ['status' => 'skipped', 'items' => [], 'message' => 'Verificação não realizada.'];
    }

    private function skippedApi(string $message): array
    {
        return ['status' => 'skipped', 'items' => [], 'reconnect_required' => true, 'message' => $message];
    }
}