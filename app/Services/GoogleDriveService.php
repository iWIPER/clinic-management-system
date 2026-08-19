<?php

namespace App\Services;

use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use Google_Service_Exception;
use App\Exceptions\DriveStructureMissingException;
use App\Exceptions\GoogleDriveReauthRequiredException;
use App\Models\Clinic;
use App\Models\DriveActivityLog;
use App\Models\Patient;
use App\Models\PatientPhoto;
use App\Models\User;

class GoogleDriveService
{
    /**
     * Clinic bound to the last getDriveForClinic() call — usado pelos
     * wrappers de folder/callDrive pra saber qual clínica passar adiante
     * (GoogleDriveCallExecutor::call() e os métodos de
     * GoogleDriveStructureService que só recebem Google_Service_Drive,
     * não Patient/Clinic). GoogleDriveAuthService e
     * GoogleDriveStructureService não guardam esse estado — recebem
     * Clinic explicitamente em cada método, de propósito (ver C1.2.2/C1.2.3).
     */
    private ?Clinic $currentClinic = null;

    public function __construct(
        private GoogleDriveAuthService $authService,
        private GoogleDriveCallExecutor $callExecutor,
        private GoogleDriveStructureService $structureService,
    ) {
    }

    /**
     * Fase C1.2.1 — seam de teste. Fase C1.2.2: o Google_Client agora vive
     * só em GoogleDriveAuthService — isto é um wrapper mínimo pra não
     * quebrar nenhum teste já escrito contra esta classe (folders, upload,
     * fotos, quota, health — nada disso foi movido nesta fase).
     */
    public function useHttpClientForTesting(\GuzzleHttp\ClientInterface $http): void
    {
        $this->authService->useHttpClientForTesting($http);
    }

    // ─── OAuth (C1.2.2: delegado a GoogleDriveAuthService) ─────────────────────

    public function getAuthUrl(): string
    {
        return $this->authService->getAuthUrl();
    }

    public function exchangeCode(string $code): array
    {
        return $this->authService->exchangeCode($code);
    }

    public function fetchEmailFromToken(array $token): ?string
    {
        return $this->authService->fetchEmailFromToken($token);
    }

    // ─── Authenticated Drive client ───────────────────────────────────────────

    /**
     * @throws GoogleDriveReauthRequiredException when there is no usable refresh token.
     */
    public function getDriveForClinic(Clinic $clinic): Google_Service_Drive
    {
        $this->currentClinic = $clinic;

        return $this->authService->getDriveForClinic($clinic);
    }

    /**
     * Used by the "Reconectar Drive" button: the OAuth flow is only triggered
     * when this returns false (Caso 3 do fluxo de reconexão).
     */
    public function tryRenewConnection(Clinic $clinic): bool
    {
        return $this->authService->tryRenewConnection($clinic);
    }

    /**
     * Run a live Drive API call, transparently refreshing the access token
     * and retrying once if the call fails due to an expired/revoked token.
     * Every raw $drive->... call in this class goes through here (Caso 1).
     *
     * Fase C1.2.3 — a lógica de retry em si mora agora em
     * GoogleDriveCallExecutor (reutilizada também por
     * GoogleDriveStructureService); este método só resolve qual Clinic
     * passar adiante a partir do estado local.
     */
    private function callDrive(callable $fn)
    {
        if (!$this->currentClinic) {
            return $fn();
        }

        return $this->callExecutor->call($this->currentClinic, $fn);
    }

    // ─── Folder helpers (Fase C1.2.3: delegado a GoogleDriveStructureService) ──

    public function locateFolder(string $name, ?string $parentId, Google_Service_Drive $drive): ?string
    {
        return $this->structureService->locateFolder($name, $parentId, $drive, $this->currentClinic);
    }

    public function folderExists(string $folderId, Google_Service_Drive $drive): bool
    {
        return $this->structureService->folderExists($folderId, $drive, $this->currentClinic);
    }

    /**
     * List non-folder files directly inside a folder (non-recursive).
     *
     * @return array<int, array{id: string, name: string, mimeType: string}>
     */
    public function listFilesInFolder(string $folderId, Google_Service_Drive $drive): array
    {
        $q = implode(' and ', [
            "'{$folderId}' in parents",
            'trashed = false',
            "mimeType != 'application/vnd.google-apps.folder'",
        ]);

        $files  = [];
        $pageToken = null;

        do {
            $result = $this->callDrive(fn () => $drive->files->listFiles([
                'q'                    => $q,
                'fields'               => 'nextPageToken, files(id, name, mimeType)',
                'pageSize'             => 100,
                'pageToken'            => $pageToken,
                'supportsAllDrives'    => true,
                'includeItemsFromAllDrives' => true,
            ]));

            foreach ($result->getFiles() as $file) {
                $files[] = [
                    'id'       => $file->getId(),
                    'name'     => $file->getName(),
                    'mimeType' => $file->getMimeType(),
                ];
            }

            $pageToken = $result->getNextPageToken();
        } while ($pageToken);

        return $files;
    }

    /**
     * List all non-folder files under a folder tree (patient storage).
     *
     * @return array<int, array{id: string, name: string, mimeType: string, folder: string}>
     */
    public function listAllPatientFiles(string $patientFolderId, Google_Service_Drive $drive): array
    {
        $collected = [];

        $walk = function (string $folderId, string $folderLabel) use ($drive, &$walk, &$collected): void {
            foreach ($this->listFilesInFolder($folderId, $drive) as $file) {
                $collected[] = array_merge($file, ['folder' => $folderLabel]);
            }

            $escaped = str_replace("'", "\\'", $folderId);
            $q = implode(' and ', [
                "mimeType = 'application/vnd.google-apps.folder'",
                "'{$escaped}' in parents",
                'trashed = false',
            ]);

            $result = $this->callDrive(fn () => $drive->files->listFiles([
                'q'      => $q,
                'fields' => 'files(id, name)',
                'pageSize' => 50,
            ]));

            foreach ($result->getFiles() as $sub) {
                $walk($sub->getId(), $sub->getName());
            }
        };

        $walk($patientFolderId, 'Paciente');

        return $collected;
    }

    /**
     * Probe Drive permissions by creating, reading, renaming, moving and deleting a temp file.
     *
     * @return array<int, array{key: string, label: string, status: string, message: ?string}>
     */
    public function probeDrivePermissions(string $parentFolderId, Google_Service_Drive $drive): array
    {
        $results = [];
        $testFileId = null;
        $testName   = '.wildental-healthcheck-' . now()->timestamp;

        $check = function (string $key, string $label, callable $fn) use (&$results): void {
            try {
                $fn();
                $results[] = ['key' => $key, 'label' => $label, 'status' => 'ok', 'message' => null];
            } catch (\Throwable $e) {
                $results[] = [
                    'key'     => $key,
                    'label'   => $label,
                    'status'  => 'fail',
                    'message' => 'Não foi possível validar esta permissão.',
                ];
            }
        };

        try {
            $created = $this->callDrive(fn () => $drive->files->create(
                new Google_Service_Drive_DriveFile([
                    'name'     => $testName,
                    'parents'  => [$parentFolderId],
                    'mimeType' => 'text/plain',
                ]),
                ['data' => 'wildental', 'uploadType' => 'multipart', 'fields' => 'id']
            ));
            $testFileId = $created->getId();
        } catch (\Throwable) {
            return [
                ['key' => 'read', 'label' => 'Permissão de leitura', 'status' => 'fail', 'message' => 'Falha ao criar arquivo de teste.'],
                ['key' => 'write', 'label' => 'Permissão de escrita', 'status' => 'fail', 'message' => null],
                ['key' => 'move', 'label' => 'Permissão para mover arquivos', 'status' => 'skipped', 'message' => 'Não testado.'],
                ['key' => 'rename', 'label' => 'Permissão para renomear', 'status' => 'skipped', 'message' => 'Não testado.'],
                ['key' => 'delete', 'label' => 'Permissão para excluir', 'status' => 'skipped', 'message' => 'Não testado.'],
            ];
        }

        $check('write', 'Permissão de escrita', fn () => true);

        $check('read', 'Permissão de leitura', function () use ($drive, $testFileId) {
            $this->callDrive(fn () => $drive->files->get($testFileId, ['fields' => 'id,name']));
        });

        $check('rename', 'Permissão para renomear', function () use ($drive, $testFileId) {
            $this->callDrive(fn () => $drive->files->update(
                $testFileId,
                new Google_Service_Drive_DriveFile(['name' => $testFileId . '-renamed']),
                ['fields' => 'id']
            ));
        });

        $check('move', 'Permissão para mover arquivos', function () use ($drive, $testFileId, $parentFolderId) {
            // Move within same parent is a no-op but validates patch permission
            $this->callDrive(fn () => $drive->files->update(
                $testFileId,
                new Google_Service_Drive_DriveFile(),
                ['addParents' => $parentFolderId, 'fields' => 'id']
            ));
        });

        // Fase C1.2.1.1 — &$testFileId por referência (achado em C1.2.1: era
        // por valor, então "$testFileId = null" só zerava a cópia local do
        // closure, nunca a variável externa — o cleanup abaixo sempre
        // tentava um segundo DELETE no mesmo arquivo já excluído).
        $check('delete', 'Permissão para excluir', function () use ($drive, &$testFileId) {
            $this->callDrive(fn () => $drive->files->delete($testFileId));
            $testFileId = null;
        });

        // Cleanup if delete failed
        if ($testFileId) {
            try {
                $this->callDrive(fn () => $drive->files->delete($testFileId));
            } catch (\Throwable) {
                // ignore
            }
        }

        return $results;
    }

    // ─── Folder hierarchy (Fase C1.2.3: delegado a GoogleDriveStructureService) ─

    /**
     * Whether this clinic/patient ever had Drive folder IDs persisted.
     */
    public function structureWasPreviouslyEstablished(Patient $patient, User $doctor): bool
    {
        return $this->structureService->structureWasPreviouslyEstablished($patient, $doctor);
    }

    /**
     * Authorized disaster recovery without upload — rebuilds only what is missing.
     */
    public function recoverStructure(Patient $patient, User $doctor): void
    {
        $this->structureService->recoverStructure($patient, $doctor);
    }

    private function logUploadResumed(Patient $patient, PatientPhoto $photo, array $metadata): void
    {
        DriveActivityLog::create([
            'clinic_id'   => $patient->clinic_id,
            'patient_id'  => $patient->id,
            'photo_id'    => $photo->id,
            'event_type'  => 'upload_resumed',
            'description' => 'Upload retomado automaticamente.',
            'metadata'    => [
                'filename'     => $photo->filename,
                'categoria'    => $photo->categoria,
                'subcategoria' => $metadata['subcategoria'] ?? null,
            ],
        ]);
    }

    // ─── Upload ───────────────────────────────────────────────────────────────

    /**
     * Resolve (and cache) the Drive folder ID that uploads for this
     * patient/categoria should land in — without uploading anything.
     *
     * Meant to be called once per request when several files will be
     * uploaded in a loop (ex: várias fotos de uma evolução clínica), so the
     * per-file existence checks + folder search in resolveExistingStructure()
     * aren't repeated for every single file.
     *
     * @return array{upload_folder_id: string, patient_folder_id: string}
     * @throws DriveStructureMissingException
     */
    public function resolveUploadFolder(Patient $patient, User $doctor, ?string $categoria = null): array
    {
        return $this->structureService->resolveUploadFolder($patient, $doctor, $categoria);
    }

    /**
     * Envia um arquivo direto pra uma pasta já resolvida do Drive — sem
     * nenhum efeito colateral no banco (não cria nem atualiza PatientPhoto).
     * Usado por UploadEvolutionPhotoJob, que gerencia sua própria linha
     * PatientPhoto (criada como 'uploading' antes do job rodar, atualizada
     * em vez de recriada) — diferente de uploadPhoto() abaixo, que sempre
     * cria a linha internamente.
     *
     * @return array{drive_file_id: string}
     * @throws DriveStructureMissingException
     * @throws GoogleDriveReauthRequiredException
     */
    public function uploadToKnownFolder(
        Patient $patient,
        string $folderId,
        string $filePath,
        string $fileName,
        string $mimeType
    ): array {
        $drive = $this->getDriveForClinic($patient->clinic);

        try {
            $uploaded = $this->performDriveUpload($drive, $folderId, $filePath, $fileName, $mimeType);
        } catch (Google_Service_Exception $e) {
            if ($e->getCode() === 404) {
                $this->structureService->logStructureNotFound($patient, 'upload', $folderId);
                throw new DriveStructureMissingException('upload', $folderId);
            }
            throw $e;
        }

        return ['drive_file_id' => $uploaded->getId()];
    }

    /**
     * @return array{photo: PatientPhoto, structure_recreated: bool}
     */
    public function uploadPhoto(
        Patient $patient,
        User $doctor,
        string $filePath,
        string $fileName,
        string $mimeType,
        array $metadata = [],
        bool $authorizeRecovery = false,
        ?string $preResolvedFolderId = null
    ): array {
        $clinic        = $patient->clinic;
        $drive         = $this->getDriveForClinic($clinic);
        $categoria     = $metadata['categoria'] ?? null;
        $recreated     = false;

        if ($preResolvedFolderId !== null && !$authorizeRecovery) {
            $folderId = $preResolvedFolderId;
        } else {
            $hadStructure = $this->structureWasPreviouslyEstablished($patient, $doctor);

            if ($hadStructure && !$authorizeRecovery) {
                $this->structureService->assertStructureAvailable($patient, $doctor);
                $resolved = $this->structureService->resolveExistingStructure($patient, $doctor, $categoria, $drive);
            } elseif ($authorizeRecovery) {
                $this->structureService->logDisasterRecoveryAuthorized($patient, $doctor);
                $repaired  = $this->structureService->rebuildAuthorizedStructure($patient, $doctor, $categoria, $drive);
                $resolved  = [
                    'upload_folder_id'  => $repaired['upload_folder_id'],
                    'patient_folder_id' => $repaired['patient_folder_id'],
                ];
                $recreated = true;
                $this->structureService->logDisasterRecoveryCompleted($patient, $repaired['recreated_levels']);
            } else {
                $resolved = $this->structureService->ensureFirstTimeStructure($patient, $doctor, $categoria, $drive);
            }

            $folderId = $resolved['upload_folder_id'];
        }

        try {
            $uploaded = $this->performDriveUpload($drive, $folderId, $filePath, $fileName, $mimeType);
        } catch (Google_Service_Exception $e) {
            if ($e->getCode() === 404 && !$authorizeRecovery) {
                $this->structureService->logStructureNotFound($patient, 'upload', $folderId);
                throw new DriveStructureMissingException('upload', $folderId);
            }
            throw $e;
        }

        $photo = PatientPhoto::create([
            'clinic_id'       => $patient->clinic_id,
            'patient_id'      => $patient->id,
            'drive_file_id'   => $uploaded->getId(),
            'drive_folder_id' => $folderId,
            'filename'        => $fileName,
            'mime_type'       => $mimeType,
            'taken_at'        => $metadata['taken_at'] ?? now(),
            'categoria'       => $categoria,
            'subcategoria'    => $metadata['subcategoria']  ?? null,
            'dente'           => $metadata['dente']         ?? null,
            'uploaded_by_id'  => $metadata['uploaded_by_id'] ?? null,
            'status'          => 'active',
        ]);

        if ($recreated) {
            $this->logUploadResumed($patient, $photo, $metadata);
        }

        return [
            'photo'               => $photo,
            'structure_recreated' => $recreated,
        ];
    }

    private function performDriveUpload(
        Google_Service_Drive $drive,
        string $folderId,
        string $filePath,
        string $fileName,
        string $mimeType
    ): Google_Service_Drive_DriveFile {
        $fileMetadata = new Google_Service_Drive_DriveFile([
            'name'    => $fileName,
            'parents' => [$folderId],
        ]);

        return $this->callDrive(fn () => $drive->files->create($fileMetadata, [
            'data'       => file_get_contents($filePath),
            'mimeType'   => $mimeType,
            'uploadType' => 'multipart',
            'fields'     => 'id',
        ]));
    }

    // ─── Serve photo ──────────────────────────────────────────────────────────

    /**
     * Stream a photo from Google Drive back to the browser.
     * Files uploaded with DRIVE_FILE scope are private — they cannot be loaded
     * directly by the browser. This method proxies the image bytes.
     */
    public function streamPhoto(PatientPhoto $photo): \Illuminate\Http\Response
    {
        $drive = $this->getDriveForClinic($photo->patient->clinic);

        /** @var \GuzzleHttp\Psr7\Response $response */
        $response = $this->callDrive(fn () => $drive->files->get($photo->drive_file_id, ['alt' => 'media']));
        $content  = $response->getBody()->getContents();

        return response($content, 200)
            ->header('Content-Type', $photo->mime_type)
            ->header('Cache-Control', 'private, max-age=3600')
            ->header('Content-Length', strlen($content));
    }

    // ─── Storage quota ────────────────────────────────────────────────────────

    /**
     * Return the clinic's Google Drive storage quota.
     * Returns null for unlimited (Workspace) accounts or on API failure.
     *
     * @return array{limit_bytes:int,usage_bytes:int,available_bytes:int,percentage:float}|null
     */
    public function getStorageQuota(Clinic $clinic): ?array
    {
        try {
            $drive = $this->getDriveForClinic($clinic);
            $about = $this->callDrive(fn () => $drive->about->get(['fields' => 'storageQuota']));
            $quota = $about->getStorageQuota();

            $limitStr = $quota->getLimit();
            $usageStr = $quota->getUsage();

            if (!$limitStr || (int) $limitStr === 0) {
                return null; // Google Workspace — unlimited
            }

            $limit = (int) $limitStr;
            $usage = (int) ($usageStr ?? 0);

            return [
                'limit_bytes'     => $limit,
                'usage_bytes'     => $usage,
                'available_bytes' => max(0, $limit - $usage),
                'percentage'      => round(min(100, ($usage / $limit) * 100), 1),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    // ─── Library sync & integrity ─────────────────────────────────────────────

    /**
     * Silently sync the patient's file library against Google Drive.
     * Moves missing files to "removed" and restores files that reappear.
     *
     * @return array{checked: int, removed: int, restored: int}
     */
    public function syncPatientLibrary(Patient $patient): array
    {
        $clinic = $patient->clinic;

        if (!$clinic?->storageConnection) {
            return ['checked' => 0, 'removed' => 0, 'restored' => 0];
        }

        try {
            $drive = $this->getDriveForClinic($clinic);
        } catch (\Throwable) {
            return ['checked' => 0, 'removed' => 0, 'restored' => 0];
        }

        $photos = $patient->photos()
            ->whereIn('status', ['active', 'removed', 'missing'])
            ->whereNotNull('drive_file_id')
            ->get();

        if ($photos->isEmpty()) {
            return ['checked' => 0, 'removed' => 0, 'restored' => 0];
        }

        $existingIds = $this->batchLookupFileIds(
            $drive,
            $photos->pluck('drive_file_id')->filter()->values()->all()
        );
        $existingSet = array_flip($existingIds);

        $removed  = 0;
        $restored = 0;

        foreach ($photos as $photo) {
            $exists = isset($existingSet[$photo->drive_file_id]);

            if ($exists && in_array($photo->status, ['removed', 'missing'], true)) {
                $photo->update(['status' => 'active']);
                $this->logFileRestored($photo);
                $restored++;
            } elseif (!$exists && $photo->status === 'active') {
                $this->markPhotoAsRemoved($photo);
                $removed++;
            } elseif (!$exists && $photo->status === 'missing') {
                $photo->update(['status' => 'removed']);
                $removed++;
            }
        }

        return [
            'checked'  => $photos->count(),
            'removed'  => $removed,
            'restored' => $restored,
        ];
    }

    /**
     * @return array{checked:int, removed:int}
     */
    public function verifyPatientPhotos(Patient $patient): array
    {
        $result = $this->syncPatientLibrary($patient);

        return [
            'checked' => $result['checked'],
            'removed' => $result['removed'],
            'missing' => $result['removed'],
        ];
    }

    /**
     * Rename a photo in Google Drive and sync the filename in the database.
     * The caller must validate the 2-hour editing window before calling this.
     */
    public function renamePhoto(
        PatientPhoto $photo,
        string $newDisplayName,
        ?string $newDente,
        ?string $newCategoria,
        ?string $description,
        ?string $observacao,
        User $user
    ): void {
        $clinic = \App\Models\Clinic::find($photo->clinic_id);
        $drive  = $this->getDriveForClinic($clinic);

        // Physical filename = name + dente only (category and description never appear)
        $ext         = pathinfo($photo->filename, PATHINFO_EXTENSION);
        $newFilename = $newDente
            ? "{$newDisplayName} - Dente {$newDente}.{$ext}"
            : "{$newDisplayName}.{$ext}";

        $oldFilename     = $photo->filename;
        $oldDente        = $photo->dente;
        $oldName         = $photo->subcategoria;
        $oldDesc         = $photo->description;
        $oldObs          = $photo->observacao;
        $oldCategoria    = $photo->categoria;
        $oldFolderId     = $photo->drive_folder_id;
        $newDriveFolderId = $oldFolderId;
        $categoryMoved   = $newCategoria && $newCategoria !== $oldCategoria;

        // Rename file in Drive when physical filename changes
        if ($newFilename !== $oldFilename) {
            $driveFile = new Google_Service_Drive_DriveFile(['name' => $newFilename]);
            $this->callDrive(fn () => $drive->files->update($photo->drive_file_id, $driveFile, ['fields' => 'id']));
        }

        // Move to new category folder when categoria changes
        if ($categoryMoved) {
            $photo->loadMissing('patient');
            abort_if(!$photo->patient?->drive_folder_id, 422, 'Estrutura de pastas do paciente não encontrada.');

            $newDriveFolderId = $this->structureService->findOrCreateFolder(
                $newCategoria,
                $photo->patient->drive_folder_id,
                $drive,
                $clinic
            );

            $this->callDrive(fn () => $drive->files->update(
                $photo->drive_file_id,
                new Google_Service_Drive_DriveFile(),
                [
                    'addParents'    => $newDriveFolderId,
                    'removeParents' => $oldFolderId,
                    'fields'        => 'id, parents',
                ]
            ));
        }

        $photo->update([
            'filename'        => $newFilename,
            'subcategoria'    => $newDisplayName,
            'categoria'       => $newCategoria ?? $oldCategoria,
            'dente'           => $newDente ?: null,
            'description'     => $description ?: null,
            'observacao'      => $observacao ?: null,
            'drive_folder_id' => $newDriveFolderId,
        ]);

        $photo->loadMissing('patient');

        $changes = [];
        if ($oldName !== $newDisplayName)                              $changes['nome']      = ['de' => $oldName, 'para' => $newDisplayName];
        if ($newCategoria && $oldCategoria !== $newCategoria)          $changes['categoria'] = ['de' => $oldCategoria, 'para' => $newCategoria];
        if ($oldDente !== ($newDente ?: null))                         $changes['dente']     = ['de' => $oldDente, 'para' => $newDente ?: null];
        if ($oldDesc !== ($description ?: null))                       $changes['descricao'] = ['de' => $oldDesc, 'para' => $description ?: null];
        if ($oldObs !== ($observacao ?: null))                         $changes['observacao']= ['de' => $oldObs, 'para' => $observacao ?: null];

        $eventType   = $categoryMoved ? 'file_moved' : 'file_renamed';
        $description_log = $categoryMoved
            ? "Documento movido de '{$oldCategoria}' para '{$newCategoria}'"
            : 'Documento editado';

        DriveActivityLog::create([
            'clinic_id'   => $photo->clinic_id,
            'patient_id'  => $photo->patient_id,
            'photo_id'    => $photo->id,
            'event_type'  => $eventType,
            'description' => $description_log,
            'metadata'    => array_filter([
                'old_filename'        => $oldFilename,
                'new_filename'        => $newFilename,
                'changes'             => $changes,
                'categoria'           => $newCategoria ?? $oldCategoria,
                'patient_name'        => $photo->patient ? trim("{$photo->patient->nome} {$photo->patient->sobrenome}") : null,
                'edited_by'           => $user->name,
                'edited_at'           => now()->toIso8601String(),
                'drive_old_folder_id' => $categoryMoved ? $oldFolderId : null,
                'drive_new_folder_id' => $categoryMoved ? $newDriveFolderId : null,
            ], fn($v) => $v !== null),
        ]);
    }

    /**
     * Permanently delete a photo from Google Drive and the database.
     * Only allowed within the 2-hour editing window (caller must validate).
     * Hard-deletes the record — does NOT set status='removed'.
     */
    public function deletePhotoFromSystem(PatientPhoto $photo, User $user): void
    {
        $clinic = \App\Models\Clinic::find($photo->clinic_id);
        $drive  = $this->getDriveForClinic($clinic);

        $photo->loadMissing('patient');
        $patient = $photo->patient;

        // Log before deletion: photo_id = null since record will be gone.
        DriveActivityLog::create([
            'clinic_id'   => $photo->clinic_id,
            'patient_id'  => $photo->patient_id,
            'photo_id'    => null,
            'event_type'  => 'file_deleted_system',
            'description' => 'Documento removido pelo sistema',
            'metadata'    => [
                'filename'      => $photo->filename,
                'subcategoria'  => $photo->subcategoria,
                'categoria'     => $photo->categoria,
                'drive_file_id' => $photo->drive_file_id,
                'patient_name'  => $patient ? trim("{$patient->nome} {$patient->sobrenome}") : null,
                'deleted_by'    => $user->name,
                'deleted_at'    => now()->toIso8601String(),
                'motivo'        => 'Exclusão realizada dentro da janela de edição (2 horas).',
            ],
        ]);

        try {
            $this->callDrive(fn () => $drive->files->delete($photo->drive_file_id));
        } catch (Google_Service_Exception $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
            // File already absent from Drive — continue with DB cleanup.
        }

        $photo->delete();
    }

    public function markPhotoAsRemoved(PatientPhoto $photo): void
    {
        if ($photo->status === 'removed') {
            return;
        }

        $photo->loadMissing('patient');
        $patient = $photo->patient;

        $photo->update(['status' => 'removed']);

        DriveActivityLog::create([
            'clinic_id'   => $photo->clinic_id,
            'patient_id'  => $photo->patient_id,
            'photo_id'    => $photo->id,
            'event_type'  => 'file_deleted',
            'description' => 'Arquivo removido do Google Drive',
            'metadata'    => [
                'patient_name'  => $patient ? trim("{$patient->nome} {$patient->sobrenome}") : null,
                'filename'      => $photo->subcategoria ?? $photo->filename,
                'categoria'     => $photo->categoria,
                'subcategoria'  => $photo->subcategoria,
                'drive_file_id' => $photo->drive_file_id,
                'detected_at'   => now()->toIso8601String(),
                'status_note'   => 'Movido automaticamente para "Removidos".',
            ],
        ]);
    }

    private function logFileRestored(PatientPhoto $photo): void
    {
        DriveActivityLog::create([
            'clinic_id'   => $photo->clinic_id,
            'patient_id'  => $photo->patient_id,
            'photo_id'    => $photo->id,
            'event_type'  => 'file_restored',
            'description' => 'Arquivo restaurado no Google Drive',
            'metadata'    => [
                'filename'      => $photo->subcategoria ?? $photo->filename,
                'categoria'     => $photo->categoria,
                'drive_file_id' => $photo->drive_file_id,
                'restored_at'   => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * @param  string[]  $fileIds
     * @return string[]
     */
    public function batchLookupFileIds(Google_Service_Drive $drive, array $fileIds): array
    {
        $existingIds = [];

        foreach (array_chunk($fileIds, 50) as $chunk) {
            $clauses = array_map(
                fn ($id) => "id = '" . str_replace("'", "\\'", $id) . "'",
                $chunk
            );
            $q = '(' . implode(' or ', $clauses) . ') and trashed = false';

            $result = $this->callDrive(fn () => $drive->files->listFiles([
                'q'        => $q,
                'fields'   => 'files(id)',
                'pageSize' => count($chunk),
            ]));

            foreach ($result->getFiles() as $file) {
                $existingIds[] = $file->getId();
            }
        }

        return $existingIds;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function listPhotos(Patient $patient)
    {
        return $patient->photos()->latest()->get();
    }
}
