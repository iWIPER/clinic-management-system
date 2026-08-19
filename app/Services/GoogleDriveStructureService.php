<?php

namespace App\Services;

use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use Google_Service_Exception;
use App\Exceptions\DriveStructureMissingException;
use Illuminate\Support\Facades\Log;
use App\Models\Clinic;
use App\Models\DriveActivityLog;
use App\Models\Patient;
use App\Models\User;

/**
 * Fase C1.2.3 — segunda extração arquitetural do GoogleDriveService.
 * Responde só por "como localizar, criar e reparar a estrutura de pastas
 * (root/profissional/paciente/categoria)" — nunca por upload/streaming/
 * foto/quota/OAuth (isso continua em GoogleDriveService).
 *
 * Sem estado de "clínica atual": os métodos de alto nível recebem Patient
 * (de onde derivam a Clinic) e os primitivos de baixo nível recebem
 * Clinic explicitamente — nenhum $currentClinic duplicado aqui (esse
 * estado é responsabilidade só de GoogleDriveService, que o usa também
 * pros métodos que não migraram nesta fase).
 *
 * Vários métodos que eram privados em GoogleDriveService (findOrCreateFolder,
 * ensureFirstTimeStructure, resolveExistingStructure, rebuildAuthorizedStructure,
 * logDisasterRecoveryAuthorized/Completed, logStructureNotFound) viraram
 * públicos aqui: GoogleDriveService::uploadPhoto()/renamePhoto() continuam
 * chamando-os diretamente (mesma orquestração de sempre, só mudou o ponto
 * de execução — nenhuma lógica foi redesenhada).
 */
class GoogleDriveStructureService
{
    public function __construct(
        private GoogleDriveAuthService $authService,
        private GoogleDriveCallExecutor $callExecutor,
    ) {
    }

    // ─── Folder primitives ──────────────────────────────────────────────────────

    /**
     * Search for a folder by exact name inside a parent (or Drive root).
     * Returns the folder ID or null if not found.
     */
    private function findFolder(string $name, ?string $parentId, Google_Service_Drive $drive, Clinic $clinic): ?string
    {
        $escaped = str_replace("'", "\\'", $name);
        $parent  = $parentId ?? 'root';

        $q = implode(' and ', [
            "mimeType = 'application/vnd.google-apps.folder'",
            "name = '{$escaped}'",
            "'{$parent}' in parents",
            'trashed = false',
        ]);

        $result = $this->callExecutor->call($clinic, fn () => $drive->files->listFiles([
            'q'        => $q,
            'fields'   => 'files(id)',
            'spaces'   => 'drive',
            'pageSize' => 1,
        ]));

        $files = $result->getFiles();

        return count($files) > 0 ? $files[0]->getId() : null;
    }

    /**
     * Create a new folder. Returns its ID.
     */
    private function createFolder(string $name, ?string $parentId, Google_Service_Drive $drive, Clinic $clinic): string
    {
        $metadata = new Google_Service_Drive_DriveFile([
            'name'     => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents'  => [$parentId ?? 'root'],
        ]);

        $folder = $this->callExecutor->call($clinic, fn () => $drive->files->create($metadata, ['fields' => 'id']));

        return $folder->getId();
    }

    /**
     * Find an existing folder or create it — prevents duplicates.
     * Público: GoogleDriveService::renamePhoto() continua chamando isto
     * diretamente pra mover uma foto de categoria.
     */
    public function findOrCreateFolder(string $name, ?string $parentId, Google_Service_Drive $drive, Clinic $clinic): string
    {
        return $this->findFolder($name, $parentId, $drive, $clinic)
            ?? $this->createFolder($name, $parentId, $drive, $clinic);
    }

    public function locateFolder(string $name, ?string $parentId, Google_Service_Drive $drive, Clinic $clinic): ?string
    {
        return $this->findFolder($name, $parentId, $drive, $clinic);
    }

    public function folderExists(string $folderId, Google_Service_Drive $drive, Clinic $clinic): bool
    {
        return $this->checkFolderExists($folderId, $drive, $clinic);
    }

    /**
     * Check whether a folder ID still exists and is not trashed.
     */
    private function checkFolderExists(string $folderId, Google_Service_Drive $drive, Clinic $clinic): bool
    {
        try {
            $file = $this->callExecutor->call($clinic, fn () => $drive->files->get($folderId, ['fields' => 'id,trashed']));
            return !(bool) $file->getTrashed();
        } catch (Google_Service_Exception $e) {
            if ($e->getCode() === 404) return false;
            throw $e;
        }
    }

    // ─── Folder hierarchy ─────────────────────────────────────────────────────

    /**
     * Whether this clinic/patient ever had Drive folder IDs persisted.
     */
    public function structureWasPreviouslyEstablished(Patient $patient, User $doctor): bool
    {
        $clinic = $patient->clinic;
        $pivot  = $doctor->clinics()->where('clinics.id', $clinic->id)->first()?->pivot;

        return (bool) (
            $clinic->storageConnection?->drive_root_folder_id
            || $pivot?->drive_doctor_folder_id
            || $patient->drive_folder_id
        );
    }

    /**
     * Detect the first missing level in a previously established structure.
     *
     * @return array{level: string, old_folder_id: ?string}|null
     */
    public function detectStructureMissing(
        Patient $patient,
        User $doctor,
        ?Google_Service_Drive $drive = null
    ): ?array {
        if (!$this->structureWasPreviouslyEstablished($patient, $doctor)) {
            return null;
        }

        $clinic     = $patient->clinic;
        $connection = $clinic->storageConnection;
        $drive    ??= $this->authService->getDriveForClinic($clinic);
        $pivot      = $doctor->clinics()->where('clinics.id', $clinic->id)->first()?->pivot;

        if ($connection->drive_root_folder_id
            && !$this->checkFolderExists($connection->drive_root_folder_id, $drive, $clinic)) {
            return ['level' => 'root', 'old_folder_id' => $connection->drive_root_folder_id];
        }

        $doctorFolder = $pivot?->drive_doctor_folder_id;
        if ($doctorFolder && !$this->checkFolderExists($doctorFolder, $drive, $clinic)) {
            return ['level' => 'clinic', 'old_folder_id' => $doctorFolder];
        }

        if ($patient->drive_folder_id
            && !$this->checkFolderExists($patient->drive_folder_id, $drive, $clinic)) {
            return ['level' => 'patient', 'old_folder_id' => $patient->drive_folder_id];
        }

        return null;
    }

    /**
     * @throws DriveStructureMissingException
     */
    public function assertStructureAvailable(Patient $patient, User $doctor): void
    {
        $missing = $this->detectStructureMissing($patient, $doctor);

        if ($missing) {
            $this->logStructureNotFound($patient, $missing['level'], $missing['old_folder_id']);
            throw new DriveStructureMissingException($missing['level'], $missing['old_folder_id']);
        }
    }

    /**
     * Público: GoogleDriveService::uploadPhoto()/uploadToKnownFolder()
     * continuam chamando isto diretamente no catch de 404.
     */
    public function logStructureNotFound(Patient $patient, string $level, ?string $oldFolderId): void
    {
        Log::warning('Estrutura Google Drive não encontrada — aguardando autorização do usuário.', [
            'clinic_id'     => $patient->clinic_id,
            'patient_id'    => $patient->id,
            'level'         => $level,
            'old_folder_id' => $oldFolderId,
        ]);

        DriveActivityLog::create([
            'clinic_id'   => $patient->clinic_id,
            'patient_id'  => $patient->id,
            'event_type'  => 'structure_not_found',
            'description' => 'Detectada ausência da estrutura Google Drive.',
            'metadata'    => [
                'level'         => $level,
                'old_folder_id' => $oldFolderId,
            ],
        ]);
    }

    /**
     * Smart partial repair: validates the folder hierarchy top-to-bottom and
     * recreates only the levels that are actually missing.
     *
     * Efficiency: as soon as the first missing level is found the descent stops,
     * since children cannot exist when their parent is gone. At most 3 API calls
     * are made during the validation phase.
     *
     * @return array{
     *   upload_folder_id: string,
     *   patient_folder_id: string,
     *   recreated_levels: string[],
     * }
     */
    private function partialRepair(
        Patient $patient,
        User $doctor,
        ?string $categoria,
        Google_Service_Drive $drive
    ): array {
        $clinic      = $patient->clinic;
        $connection  = $clinic->storageConnection;
        $pivot       = $doctor->clinics()->where('clinics.id', $clinic->id)->first()?->pivot;
        $patientName = trim("{$patient->nome} {$patient->sobrenome}");
        $recreated   = [];
        $rebuildFrom = null;

        // Level 1 — Wildental root
        $rootId = $connection->drive_root_folder_id;
        if (!$rootId || !$this->checkFolderExists($rootId, $drive, $clinic)) {
            $rebuildFrom = 'root';
        }

        // Level 2 — Professional folder (skipped when root is already gone)
        if ($rebuildFrom === null) {
            $doctorId = $pivot?->drive_doctor_folder_id;
            if (!$doctorId || !$this->checkFolderExists($doctorId, $drive, $clinic)) {
                $rebuildFrom = 'doctor';
            }
        }

        // Level 3 — Patient folder (skipped when any ancestor is already gone)
        if ($rebuildFrom === null) {
            $patientId = $patient->drive_folder_id;
            if (!$patientId || !$this->checkFolderExists($patientId, $drive, $clinic)) {
                $rebuildFrom = 'patient';
            }
        }

        // Rebuild only from the first missing level downward
        if ($rebuildFrom !== null) {
            // Root — recreate if it was the missing level
            if ($rebuildFrom === 'root') {
                $rootId = $this->findOrCreateFolder('Wildental', null, $drive, $clinic);
                $connection->update(['drive_root_folder_id' => $rootId]);
                $recreated[] = 'root';
            } else {
                $rootId = $connection->drive_root_folder_id;
            }

            // Professional — recreate if it or any ancestor was missing
            if (in_array($rebuildFrom, ['root', 'doctor'], true)) {
                $doctorId = $this->findOrCreateFolder($doctor->name, $rootId, $drive, $clinic);
                $doctor->clinics()->updateExistingPivot($clinic->id, [
                    'drive_doctor_folder_id' => $doctorId,
                ]);
                $recreated[] = 'doctor';
            } else {
                $doctorId = $pivot?->drive_doctor_folder_id;
            }

            // Patient — always recreated once any ancestor is missing
            $patientId = $this->findOrCreateFolder($patientName, $doctorId, $drive, $clinic);
            $patient->update(['drive_folder_id' => $patientId]);
            $patient->refresh();
            $recreated[] = 'patient';
        } else {
            $patientId = $patient->drive_folder_id;
        }

        // Level 4 — Category subfolder (find or create; never duplicates)
        $uploadFolderId = $patientId;
        if ($categoria) {
            $uploadFolderId = $this->findOrCreateFolder($categoria, $patientId, $drive, $clinic);
        }

        return [
            'upload_folder_id'  => $uploadFolderId,
            'patient_folder_id' => $patientId,
            'recreated_levels'  => $recreated,
        ];
    }

    /**
     * Build folder hierarchy for first-time setup (no cached IDs).
     * Público: GoogleDriveService::uploadPhoto() continua chamando isto
     * diretamente.
     *
     * @return array{upload_folder_id: string, patient_folder_id: string}
     */
    public function ensureFirstTimeStructure(
        Patient $patient,
        User $doctor,
        ?string $categoria,
        Google_Service_Drive $drive
    ): array {
        $clinic      = $patient->clinic;
        $connection  = $clinic->storageConnection;
        $patientName = trim("{$patient->nome} {$patient->sobrenome}");

        $rootId = $this->findOrCreateFolder('Wildental', null, $drive, $clinic);
        $connection->update(['drive_root_folder_id' => $rootId]);

        $doctorFolder = $this->findOrCreateFolder($doctor->name, $rootId, $drive, $clinic);
        $doctor->clinics()->updateExistingPivot($clinic->id, [
            'drive_doctor_folder_id' => $doctorFolder,
        ]);

        $patientFolder = $this->findOrCreateFolder($patientName, $doctorFolder, $drive, $clinic);
        $patient->update(['drive_folder_id' => $patientFolder]);

        $uploadFolderId = $categoria
            ? $this->findOrCreateFolder($categoria, $patientFolder, $drive, $clinic)
            : $patientFolder;

        return [
            'upload_folder_id'  => $uploadFolderId,
            'patient_folder_id' => $patientFolder,
        ];
    }

    /**
     * Use validated cached folder IDs and ensure category subfolder exists.
     * Público: GoogleDriveService::uploadPhoto() continua chamando isto
     * diretamente.
     *
     * @return array{upload_folder_id: string, patient_folder_id: string}
     */
    public function resolveExistingStructure(
        Patient $patient,
        User $doctor,
        ?string $categoria,
        Google_Service_Drive $drive
    ): array {
        $clinic     = $patient->clinic;
        $connection = $clinic->storageConnection;
        $pivot      = $doctor->clinics()->where('clinics.id', $clinic->id)->first()?->pivot;

        $patientFolder = $patient->drive_folder_id;
        $uploadFolderId = $categoria
            ? $this->findOrCreateFolder($categoria, $patientFolder, $drive, $clinic)
            : $patientFolder;

        return [
            'upload_folder_id'  => $uploadFolderId,
            'patient_folder_id' => $patientFolder,
        ];
    }

    /**
     * Authorized disaster recovery without upload — rebuilds only what is missing.
     */
    public function recoverStructure(Patient $patient, User $doctor): void
    {
        $drive  = $this->authService->getDriveForClinic($patient->clinic);
        $this->logDisasterRecoveryAuthorized($patient, $doctor);
        $result = $this->rebuildAuthorizedStructure($patient, $doctor, null, $drive);
        $this->logDisasterRecoveryCompleted($patient, $result['recreated_levels']);
    }

    /**
     * Authorized disaster recovery — rebuilds only the missing levels of the hierarchy.
     * Público: GoogleDriveService::uploadPhoto() continua chamando isto
     * diretamente.
     *
     * @return array{upload_folder_id: string, patient_folder_id: string, recreated_levels: string[]}
     */
    public function rebuildAuthorizedStructure(
        Patient $patient,
        User $doctor,
        ?string $categoria,
        Google_Service_Drive $drive
    ): array {
        return $this->partialRepair($patient, $doctor, $categoria, $drive);
    }

    /**
     * Público: GoogleDriveService::uploadPhoto() continua chamando isto
     * diretamente no branch de authorizeRecovery.
     */
    public function logDisasterRecoveryAuthorized(Patient $patient, User $doctor): void
    {
        DriveActivityLog::create([
            'clinic_id'   => $patient->clinic_id,
            'patient_id'  => $patient->id,
            'event_type'  => 'structure_recovery_authorized',
            'description' => 'Usuário autorizou a recriação da estrutura.',
            'metadata'    => ['authorized_by_id' => $doctor->id],
        ]);
    }

    /**
     * Público: GoogleDriveService::uploadPhoto() continua chamando isto
     * diretamente no branch de authorizeRecovery.
     */
    public function logDisasterRecoveryCompleted(Patient $patient, array $recreatedLevels = []): void
    {
        $description = match(true) {
            in_array('root', $recreatedLevels, true)    => 'Estrutura completa recriada com sucesso.',
            in_array('doctor', $recreatedLevels, true)  => 'Pasta do profissional e do paciente recriadas com sucesso.',
            in_array('patient', $recreatedLevels, true) => 'Pasta do paciente recriada com sucesso.',
            default                                     => 'Estrutura recriada com sucesso.',
        };

        DriveActivityLog::create([
            'clinic_id'   => $patient->clinic_id,
            'patient_id'  => $patient->id,
            'event_type'  => 'structure_recreated',
            'description' => $description,
            'metadata'    => ['recreated_levels' => $recreatedLevels],
        ]);
    }

    private function logStructureVerifyRepaired(
        Patient $patient,
        array $recreatedLevels,
        array $recreatedCategories
    ): void {
        $description = match(true) {
            in_array('root', $recreatedLevels, true)    => 'Verificação detectou e recriou a estrutura completa.',
            in_array('doctor', $recreatedLevels, true)  => 'Verificação detectou e recriou pasta do profissional e do paciente.',
            in_array('patient', $recreatedLevels, true) => 'Verificação detectou e recriou pasta do paciente.',
            !empty($recreatedCategories)                => 'Verificação detectou e recriou categoria(s) ausente(s).',
            default                                     => 'Verificação detectou e recriou partes da estrutura.',
        };

        DriveActivityLog::create([
            'clinic_id'   => $patient->clinic_id,
            'patient_id'  => $patient->id,
            'event_type'  => 'structure_recreated',
            'description' => $description,
            'metadata'    => [
                'recreated_levels'     => $recreatedLevels,
                'recreated_categories' => $recreatedCategories,
                'triggered_by'         => 'verify',
            ],
        ]);
    }

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
        $drive = $this->authService->getDriveForClinic($patient->clinic);

        if ($this->structureWasPreviouslyEstablished($patient, $doctor)) {
            $this->assertStructureAvailable($patient, $doctor);

            return $this->resolveExistingStructure($patient, $doctor, $categoria, $drive);
        }

        return $this->ensureFirstTimeStructure($patient, $doctor, $categoria, $drive);
    }

    /**
     * Validate the full folder hierarchy and auto-repair anything missing.
     * Also checks and recreates category subfolders found in the patient's photos.
     *
     * Never recreates what already exists. Never creates duplicates.
     *
     * @return array{
     *   missing: bool,
     *   level: ?string,
     *   recreated: string[],
     *   recreated_categories: string[],
     *   intact: bool,
     * }
     */
    public function verifyFolderStructure(Patient $patient, User $doctor): array
    {
        if (!$this->structureWasPreviouslyEstablished($patient, $doctor)) {
            return [
                'missing'              => false,
                'level'                => null,
                'recreated'            => [],
                'recreated_categories' => [],
                'intact'               => true,
            ];
        }

        $clinic = $patient->clinic;
        $drive  = $this->authService->getDriveForClinic($clinic);
        $repair = $this->partialRepair($patient, $doctor, null, $drive);

        $recreated       = $repair['recreated_levels'];
        $patientFolderId = $repair['patient_folder_id'];
        $recreatedCats   = [];

        // Check every category folder referenced by the patient's photos
        $categories = $patient->photos()
            ->whereNotNull('categoria')
            ->distinct()
            ->pluck('categoria');

        foreach ($categories as $categoria) {
            if ($this->findFolder($categoria, $patientFolderId, $drive, $clinic) === null) {
                $this->createFolder($categoria, $patientFolderId, $drive, $clinic);
                $recreatedCats[] = $categoria;
            }
        }

        if (!empty($recreated) || !empty($recreatedCats)) {
            $this->logStructureVerifyRepaired($patient, $recreated, $recreatedCats);
        }

        return [
            'missing'              => false,
            'level'                => null,
            'recreated'            => $recreated,
            'recreated_categories' => $recreatedCats,
            'intact'               => empty($recreated) && empty($recreatedCats),
        ];
    }
}
