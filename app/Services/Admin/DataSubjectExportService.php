<?php

namespace App\Services\Admin;

use App\Jobs\GenerateDataSubjectExportJob;
use App\Models\AnamnesisAlert;
use App\Models\AnamnesisInstance;
use App\Models\AnamnesisSignature;
use App\Models\Appointment;
use App\Models\Budget;
use App\Models\ClinicalEvolution;
use App\Models\ClinicalRecord;
use App\Models\Consultation;
use App\Models\DataSubjectExport;
use App\Models\Document;
use App\Models\DocumentActivityLog;
use App\Models\DocumentSignature;
use App\Models\DriveActivityLog;
use App\Models\FinancingActivityLog;
use App\Models\FinancingProposal;
use App\Models\FinancingSimulation;
use App\Models\Patient;
use App\Models\PatientInvite;
use App\Models\PatientNote;
use App\Models\PatientOdontogram;
use App\Models\PatientPayment;
use App\Models\PatientPhoto;
use App\Models\PatientTreatment;
use App\Models\ProcedureExecution;
use App\Models\Transaction;
use App\Models\User;
use App\Scopes\ClinicScope;
use App\Services\GoogleDriveService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

/**
 * Ferramenta interna do Backoffice para atender solicitações LGPD recebidas
 * por chat/suporte — nunca autoatendimento do titular. Sempre exportação
 * pontual por ID (nunca em massa; ver App\Services\Admin\ExportService, que
 * deliberadamente não tem dataset de pacientes por minimização de dados).
 *
 * Bypass de ClinicScope confinado inteiramente a esta classe: rotas /admin
 * rodam sem current_clinic_id na sessão por design (System Admin é uma
 * camada acima de clínica), então Patient::find() aqui sempre voltaria
 * vazio (ClinicScope é fail-closed) sem esse bypass explícito e nomeado.
 * Nunca reexpor esse padrão fora do namespace Admin/deste service.
 */
class DataSubjectExportService
{
    public function __construct(private GoogleDriveService $drive) {}

    public function searchUsers(string $term): array
    {
        $like = '%' . mb_strtolower($term) . '%';

        return User::query()
            ->where(fn ($q) => $q
                ->whereRaw('LOWER(name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                ->orWhereRaw('LOWER(cpf) LIKE ?', [$like]))
            ->with('clinics:id,name,trade_name')
            ->limit(20)
            ->get()
            ->map(fn (User $u) => [
                'id'      => $u->id,
                'name'    => $u->name,
                'email'   => $u->email,
                'cpf'     => $u->cpf,
                'clinics' => $u->clinics->map(fn ($c) => $c->trade_name ?? $c->name)->values(),
            ])
            ->all();
    }

    public function searchPatients(string $term): array
    {
        $like = '%' . mb_strtolower($term) . '%';

        return Patient::withoutGlobalScope(ClinicScope::class)
            ->where(fn ($q) => $q
                ->whereRaw('LOWER(nome) LIKE ?', [$like])
                ->orWhereRaw('LOWER(sobrenome) LIKE ?', [$like])
                ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                ->orWhereRaw('LOWER(cpf) LIKE ?', [$like]))
            ->with('clinic:id,name,trade_name')
            ->limit(20)
            ->get()
            ->map(fn (Patient $p) => [
                'id'     => $p->id,
                'name'   => $p->nome_completo,
                'email'  => $p->email,
                'cpf'    => $p->cpf,
                'clinic' => $p->clinic?->trade_name ?? $p->clinic?->name,
            ])
            ->all();
    }

    public function findPatientForExport(int $patientId): Patient
    {
        return Patient::withoutGlobalScope(ClinicScope::class)->findOrFail($patientId);
    }

    /**
     * Remove do conteúdo exportado tokens técnicos que funcionam como
     * credencial de acesso a fluxos públicos sem login (wizard de convite,
     * validação de documento/anamnese, assinatura remota) — nunca dado
     * pessoal do titular. Sanitização confinada a este export: os models
     * continuam sem $hidden porque o token é necessário no resto do app
     * (ex.: gerar o link de validação/assinatura para o paciente real).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  list<string>  $tokenFields
     * @return array<int, array<string, mixed>>
     */
    private function withoutAccessTokens(array $rows, array $tokenFields): array
    {
        return array_map(fn (array $row) => Arr::except($row, $tokenFields), $rows);
    }

    /**
     * Exportação simples do titular-usuário — dado trivial (uma linha),
     * síncrona, sem job. Só os campos fillable do próprio User (password/
     * remember_token já são $hidden e nunca aparecem em toArray()).
     */
    public function exportUser(User $user): array
    {
        return [
            'gerado_em' => now()->toIso8601String(),
            'usuario'   => $user->toArray(),
            'clinicas'  => $user->clinics()
                ->select('clinics.id', 'clinics.name', 'clinics.trade_name')
                ->withPivot('role', 'created_at as joined_at')
                ->get()
                ->map(fn ($c) => [
                    'id'        => $c->id,
                    'nome'      => $c->trade_name ?? $c->name,
                    'papel'     => $c->pivot->role,
                    'desde'     => $c->pivot->joined_at,
                ]),
        ];
    }

    public function startPatientExport(Patient $patient, User $admin): DataSubjectExport
    {
        $export = DataSubjectExport::create([
            'subject_type'     => DataSubjectExport::SUBJECT_PATIENT,
            'subject_id'       => $patient->id,
            'clinic_id'        => $patient->clinic_id,
            'requested_by_id'  => $admin->id,
            'generation_status' => DataSubjectExport::GENERATION_PROCESSING,
        ]);

        GenerateDataSubjectExportJob::dispatch($export->id);

        return $export;
    }

    /**
     * Chamado pelo Job (fora do ciclo de request). Monta o ZIP completo,
     * sobe pro S3 (disco compartilhado entre os containers web/worker do
     * Fargate — nunca disco local, que é efêmero por container) e marca o
     * registro como pronto. Idempotente: no-op se já está 'ready'.
     */
    public function buildPatientExportZip(DataSubjectExport $export): void
    {
        if ($export->generation_status === DataSubjectExport::GENERATION_READY) {
            return;
        }

        $patient = $this->findPatientForExport($export->subject_id);
        $failures = [];

        $tmpDir = sys_get_temp_dir() . '/data-subject-export-' . $export->id . '-' . uniqid();
        mkdir($tmpDir, 0700, true);
        $zipPath = $tmpDir . '/export.zip';

        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);

        $counts = [];

        $addJson = function (string $entryPath, mixed $data) use ($zip, &$counts) {
            $category = explode('/', $entryPath)[0];
            $counts[$category] = ($counts[$category] ?? 0) + (is_countable($data) ? count($data) : 1);
            $zip->addFromString($entryPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        };

        // perfil
        $addJson('perfil/dados-cadastrais.json', $patient->toArray());

        // agenda
        $appointments = Appointment::where('patient_id', $patient->id)
            ->with(['professional:id,name', 'treatment:id,nome', 'appointmentReturn'])
            ->get();
        $addJson('agenda/agendamentos.json', $appointments->toArray());

        // atendimentos — Consultation é só registro operacional interno de
        // check-in (Appointment é a fonte de verdade da consulta, ver
        // 'agenda/agendamentos.json' acima); ProcedureExecution não é mais
        // alcançado por dentro de Consultation (FK antiga consultation_id
        // saiu), por isso vira um bloco próprio, ligado direto aos
        // agendamentos do paciente.
        $consultations = Consultation::where('patient_id', $patient->id)
            ->with('professional:id,name')
            ->get();
        $addJson('atendimentos/consultas.json', $consultations->toArray());

        $procedureExecutions = ProcedureExecution::whereIn('appointment_id', $appointments->pluck('id'))
            ->with('treatment:id,nome')
            ->get();
        $addJson('atendimentos/procedimentos.json', $procedureExecutions->toArray());

        // prontuário — módulo antigo (PatientAnamnesis/PatientProntuarioController)
        // removido; evolução clínica e odontograma continuam existindo como
        // funcionalidades próprias (não fazem mais parte de nenhum "módulo
        // Prontuário"), export inalterado.
        $clinicalRecords = ClinicalRecord::where('patient_id', $patient->id)->with('professional:id,name')->get();
        $addJson('prontuario/prontuario.json', $clinicalRecords->toArray());

        $evolutions = ClinicalEvolution::where('patient_id', $patient->id)->with(['professional:id,name', 'signature'])->get();
        $addJson('prontuario/evolucoes.json', $evolutions->toArray());

        $odontogram = PatientOdontogram::where('patient_id', $patient->id)->first();
        $addJson('prontuario/odontograma.json', $odontogram?->toArray() ?? []);

        // anamnese
        $instances = AnamnesisInstance::where('patient_id', $patient->id)
            ->with(['professional:id,name', 'answers', 'alerts'])
            ->get();
        $instanceIds = $instances->pluck('id');
        $anamnesisSignatures = AnamnesisSignature::whereIn('instance_id', $instanceIds)->get();
        $addJson('anamnese/instancias.json', $this->withoutAccessTokens($instances->toArray(), ['validation_token']));
        $addJson('anamnese/assinaturas.json', $anamnesisSignatures->toArray());
        $alertsFromScope = AnamnesisAlert::where('patient_id', $patient->id)->get();
        $addJson('anamnese/alertas.json', $alertsFromScope->toArray());

        // tratamentos
        $treatments = PatientTreatment::where('patient_id', $patient->id)
            ->with(['treatment:id,nome', 'professional:id,name', 'auditLogs'])
            ->get();
        $addJson('tratamentos/tratamentos.json', $treatments->toArray());

        // orçamentos
        $budgets = Budget::where('patient_id', $patient->id)->with('items.treatment:id,nome')->get();
        $addJson('orcamentos/orcamentos.json', $budgets->toArray());

        // financeiro
        $payments = PatientPayment::where('patient_id', $patient->id)->get();
        $addJson('financeiro/pagamentos.json', $payments->toArray());

        $transactions = Transaction::where('patient_id', $patient->id)->get();
        $addJson('financeiro/lancamentos.json', $transactions->toArray());

        $simulations = FinancingSimulation::where('patient_id', $patient->id)->get();
        $proposals = FinancingProposal::where('patient_id', $patient->id)->get();
        $financingLogs = FinancingActivityLog::where('patient_id', $patient->id)->get();
        $addJson('financeiro/financiamentos.json', [
            'simulacoes' => $simulations->toArray(),
            'propostas'  => $proposals->toArray(),
            'eventos'    => $financingLogs->toArray(),
        ]);

        // documentos
        $documents = Document::where('patient_id', $patient->id)->with(['professional:id,name', 'signatures'])->get();
        $documentActivity = DocumentActivityLog::where('patient_id', $patient->id)->get();
        $addJson('documentos/documentos.json', $this->withoutAccessTokens($documents->toArray(), ['validation_token', 'signature_token']));
        $addJson('documentos/atividade.json', $documentActivity->toArray());

        foreach ($documents as $document) {
            if (! $document->pdf_path) {
                continue;
            }
            try {
                if (Storage::disk('s3')->exists($document->pdf_path)) {
                    $zip->addFromString(
                        'documentos/arquivos/' . $document->document_code . '.pdf',
                        Storage::disk('s3')->get($document->pdf_path)
                    );
                }
            } catch (\Throwable $e) {
                $failures[] = ['tipo' => 'documento', 'id' => $document->id, 'motivo' => $e->getMessage()];
            }
        }

        // assinaturas (imagens PNG, disco public local)
        $signatureModels = DocumentSignature::whereIn('document_id', $documents->pluck('id'))->get();
        foreach ($signatureModels as $signature) {
            if (! $signature->signature_path) {
                continue;
            }
            try {
                if (Storage::disk('public')->exists($signature->signature_path)) {
                    $zip->addFromString(
                        'assinaturas/arquivos/' . $signature->id . '.png',
                        Storage::disk('public')->get($signature->signature_path)
                    );
                }
            } catch (\Throwable $e) {
                $failures[] = ['tipo' => 'assinatura', 'id' => $signature->id, 'motivo' => $e->getMessage()];
            }
        }

        // fotos (Google Drive por clínica — best-effort)
        $photos = PatientPhoto::where('patient_id', $patient->id)->get();
        $driveLogs = DriveActivityLog::where('patient_id', $patient->id)->get();
        $addJson('fotos/fotos.json', $photos->toArray());
        $addJson('fotos/atividade.json', $driveLogs->toArray());

        $connection = $patient->clinic?->storageConnection;
        $driveActive = $connection && $connection->status === 'active';

        foreach ($photos as $photo) {
            if (! $driveActive || ! $photo->drive_file_id) {
                if ($photo->drive_file_id) {
                    $failures[] = ['tipo' => 'foto', 'id' => $photo->id, 'motivo' => 'Google Drive da clínica não está conectado'];
                }
                continue;
            }
            try {
                $response = $this->drive->streamPhoto($photo);
                $ext = str_contains((string) $photo->mime_type, 'png') ? 'png' : 'jpg';
                $zip->addFromString('fotos/arquivos/' . $photo->id . '.' . $ext, $response->getContent());
            } catch (\Throwable $e) {
                $failures[] = ['tipo' => 'foto', 'id' => $photo->id, 'motivo' => $e->getMessage()];
            }
        }

        // notas
        $notes = PatientNote::where('patient_id', $patient->id)->with('author:id,name')->get();
        $addJson('notas/notas.json', $notes->toArray());

        // convite
        $invites = PatientInvite::where('patient_id', $patient->id)->with('activityLogs')->get();
        $addJson('convite/convites.json', $this->withoutAccessTokens($invites->toArray(), ['token']));

        // manifest
        $zip->addFromString('manifest.json', json_encode([
            'gerado_em'      => now()->toIso8601String(),
            'gerado_por'     => $export->requestedBy?->name,
            'paciente_id'    => $patient->id,
            'clinica_id'     => $patient->clinic_id,
            'contagem'       => $counts,
            'itens_com_falha' => $failures,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $zip->close();

        $storagePath = 'data-subject-exports/patient-' . $patient->id . '-' . $export->id . '.zip';
        Storage::disk('s3')->put($storagePath, file_get_contents($zipPath));
        $fileSize = filesize($zipPath);

        @unlink($zipPath);
        @rmdir($tmpDir);

        $export->update([
            'generation_status' => DataSubjectExport::GENERATION_READY,
            'storage_path'      => $storagePath,
            'file_size'         => $fileSize,
        ]);
    }
}
