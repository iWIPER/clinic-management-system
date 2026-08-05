<?php

namespace App\Services;

use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use Google_Service_Exception;
use Google_Service_Oauth2;
use App\Exceptions\DriveStructureMissingException;
use App\Exceptions\GoogleDriveReauthRequiredException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use App\Models\Clinic;
use App\Models\ClinicStorageConnection;
use App\Models\DriveActivityLog;
use App\Models\Patient;
use App\Models\PatientPhoto;
use App\Models\User;

class GoogleDriveService
{
    private Google_Client $client;

    /**
     * Clinic bound to the last getDriveForClinic() call — used by callDrive()
     * to know which connection to refresh when an auth error is hit mid-call.
     */
    private ?Clinic $currentClinic = null;

    public function __construct()
    {
        $this->client = new Google_Client();
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setRedirectUri(config('services.google.redirect'));
        $this->client->addScope(Google_Service_Drive::DRIVE_FILE);
        $this->client->addScope(Google_Service_Oauth2::USERINFO_EMAIL);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
    }

    // ─── OAuth ────────────────────────────────────────────────────────────────

    public function getAuthUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    public function exchangeCode(string $code): array
    {
        return $this->client->fetchAccessTokenWithAuthCode($code);
    }

    /**
     * Return the Google account email from a freshly issued token array.
     * Called once during the OAuth callback — no extra HTTP round-trip needed
     * because the id_token is already in the token response.
     */
    public function fetchEmailFromToken(array $token): ?string
    {
        try {
            $payload = $this->client->verifyIdToken($token['id_token'] ?? null);
            return $payload['email'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    // ─── Authenticated Drive client ───────────────────────────────────────────

    /**
     * Return an authenticated Google_Service_Drive for the given clinic.
     * Restores the cached access token and refreshes proactively when expired.
     *
     * @throws GoogleDriveReauthRequiredException when there is no usable refresh token.
     */
    public function getDriveForClinic(Clinic $clinic): Google_Service_Drive
    {
        $this->currentClinic = $clinic;
        $connection = $clinic->storageConnection;

        if (!$connection || !$connection->refresh_token) {
            throw new GoogleDriveReauthRequiredException($clinic);
        }

        $tokenRestored = false;

        if ($connection->access_token) {
            try {
                $cached = json_decode(Crypt::decryptString($connection->access_token), true);
                $this->client->setAccessToken($cached);
                $tokenRestored = true;
            } catch (\Throwable) {
                // Corrupted cache — will refresh below
            }
        }

        if (!$tokenRestored || $this->client->isAccessTokenExpired()) {
            $this->forceRefreshAccessToken($clinic);
        }

        return new Google_Service_Drive($this->client);
    }

    /**
     * Attempt to renew the Drive connection using only the stored refresh token,
     * without opening the Google consent screen.
     *
     * Used by the "Reconectar Drive" button: the OAuth flow is only triggered
     * when this returns false (Caso 3 do fluxo de reconexão).
     */
    public function tryRenewConnection(Clinic $clinic): bool
    {
        $connection = $clinic->storageConnection;

        if (!$connection || !$connection->refresh_token) {
            return false;
        }

        $this->currentClinic = $clinic;

        try {
            $this->forceRefreshAccessToken($clinic);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Force a refresh-token exchange, bypassing the local expiry cache.
     * Called both proactively (cached token expired) and reactively
     * (a live Drive call came back with an auth error).
     *
     * @throws GoogleDriveReauthRequiredException when the refresh token itself is invalid.
     */
    private function forceRefreshAccessToken(Clinic $clinic): void
    {
        $connection = $clinic->storageConnection;

        if (!$connection || !$connection->refresh_token) {
            throw new GoogleDriveReauthRequiredException($clinic);
        }

        try {
            $refreshToken = Crypt::decryptString($connection->refresh_token);
        } catch (\Throwable) {
            $this->clearInvalidTokens($connection);
            throw new GoogleDriveReauthRequiredException($clinic);
        }

        $newToken = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);

        if (isset($newToken['error'])) {
            if (in_array($newToken['error'], ['invalid_grant', 'invalid_client'], true)) {
                $this->clearInvalidTokens($connection);
                throw new GoogleDriveReauthRequiredException($clinic);
            }

            throw new \RuntimeException(
                'Falha ao renovar token do Google Drive: '
                . ($newToken['error_description'] ?? $newToken['error'])
            );
        }

        $connection->update([
            'access_token' => Crypt::encryptString(json_encode($this->client->getAccessToken())),
            'expires_at'   => now()->addSeconds($newToken['expires_in'] ?? 3600),
        ]);
    }

    /**
     * Refresh token confirmed dead — drop only the token material and mark
     * the connection as needing reauth. Account config (google_email,
     * drive_root_folder_id, provider) is preserved (Caso 2).
     */
    private function clearInvalidTokens(ClinicStorageConnection $connection): void
    {
        $connection->update([
            'access_token'  => null,
            'refresh_token' => null,
            'expires_at'    => null,
            'status'        => 'reauth_required',
        ]);

        Log::warning('Refresh Token do Google Drive inválido — tokens removidos, reautenticação necessária.', [
            'clinic_id' => $connection->clinic_id,
        ]);
    }

    /**
     * Run a live Drive API call, transparently refreshing the access token
     * and retrying once if the call fails due to an expired/revoked token.
     * Every raw $drive->... call in this class goes through here (Caso 1).
     */
    private function callDrive(callable $fn)
    {
        try {
            return $fn();
        } catch (Google_Service_Exception $e) {
            if (!$this->isAuthError($e) || !$this->currentClinic) {
                throw $e;
            }

            $this->forceRefreshAccessToken($this->currentClinic);

            return $fn();
        }
    }

    private function isAuthError(Google_Service_Exception $e): bool
    {
        if ($e->getCode() === 401) {
            return true;
        }

        foreach ($e->getErrors() as $error) {
            if (($error['reason'] ?? null) === 'authError') {
                return true;
            }
        }

        return false;
    }

    // ─── Folder helpers ───────────────────────────────────────────────────────

    /**
     * Search for a folder by exact name inside a parent (or Drive root).
     * Returns the folder ID or null if not found.
     */
    private function findFolder(string $name, ?string $parentId, Google_Service_Drive $drive): ?string
    {
        $escaped = str_replace("'", "\\'", $name);
        $parent  = $parentId ?? 'root';

        $q = implode(' and ', [
            "mimeType = 'application/vnd.google-apps.folder'",
            "name = '{$escaped}'",
            "'{$parent}' in parents",
            'trashed = false',
        ]);

        $result = $this->callDrive(fn () => $drive->files->listFiles([
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
    private function createFolder(string $name, ?string $parentId, Google_Service_Drive $drive): string
    {
        $metadata = new Google_Service_Drive_DriveFile([
            'name'     => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents'  => [$parentId ?? 'root'],
        ]);

        $folder = $this->callDrive(fn () => $drive->files->create($metadata, ['fields' => 'id']));

        return $folder->getId();
    }

    /**
     * Find an existing folder or create it — prevents duplicates.
     */
    private function findOrCreateFolder(string $name, ?string $parentId, Google_Service_Drive $drive): string
    {
        return $this->findFolder($name, $parentId, $drive)
            ?? $this->createFolder($name, $parentId, $drive);
    }

    public function locateFolder(string $name, ?string $parentId, Google_Service_Drive $drive): ?string
    {
        return $this->findFolder($name, $parentId, $drive);
    }

    public function folderExists(string $folderId, Google_Service_Drive $drive): bool
    {
        return $this->checkFolderExists($folderId, $drive);
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
        $testName   = '.cliniflow-healthcheck-' . now()->timestamp;

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
                ['data' => 'cliniflow', 'uploadType' => 'multipart', 'fields' => 'id']
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

        $check('delete', 'Permissão para excluir', function () use ($drive, $testFileId) {
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

    /**
     * Check whether a folder ID still exists and is not trashed.
     */
    private function checkFolderExists(string $folderId, Google_Service_Drive $drive): bool
    {
        try {
            $file = $this->callDrive(fn () => $drive->files->get($folderId, ['fields' => 'id,trashed']));
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
        $drive    ??= $this->getDriveForClinic($clinic);
        $pivot      = $doctor->clinics()->where('clinics.id', $clinic->id)->first()?->pivot;

        if ($connection->drive_root_folder_id
            && !$this->checkFolderExists($connection->drive_root_folder_id, $drive)) {
            return ['level' => 'root', 'old_folder_id' => $connection->drive_root_folder_id];
        }

        $doctorFolder = $pivot?->drive_doctor_folder_id;
        if ($doctorFolder && !$this->checkFolderExists($doctorFolder, $drive)) {
            return ['level' => 'clinic', 'old_folder_id' => $doctorFolder];
        }

        if ($patient->drive_folder_id
            && !$this->checkFolderExists($patient->drive_folder_id, $drive)) {
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

    public function notifyStructureMissing(Patient $patient, User $doctor): bool
    {
        $missing = $this->detectStructureMissing($patient, $doctor);

        if (!$missing) {
            return false;
        }

        $this->logStructureNotFound($patient, $missing['level'], $missing['old_folder_id']);

        return true;
    }

    private function logStructureNotFound(Patient $patient, string $level, ?string $oldFolderId): void
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
     * Clear stale cached IDs before authorized recreation.
     * Kept for compatibility — no longer called in the main recovery path.
     */
    private function clearFolderIds(Clinic $clinic, Patient $patient, User $doctor): void
    {
        $clinic->storageConnection?->update(['drive_root_folder_id' => null]);
        $doctor->clinics()->updateExistingPivot($clinic->id, ['drive_doctor_folder_id' => null]);
        $patient->update(['drive_folder_id' => null]);
        $patient->refresh();
        $clinic->storageConnection?->refresh();
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

        // Level 1 — ClinicFlow root
        $rootId = $connection->drive_root_folder_id;
        if (!$rootId || !$this->checkFolderExists($rootId, $drive)) {
            $rebuildFrom = 'root';
        }

        // Level 2 — Professional folder (skipped when root is already gone)
        if ($rebuildFrom === null) {
            $doctorId = $pivot?->drive_doctor_folder_id;
            if (!$doctorId || !$this->checkFolderExists($doctorId, $drive)) {
                $rebuildFrom = 'doctor';
            }
        }

        // Level 3 — Patient folder (skipped when any ancestor is already gone)
        if ($rebuildFrom === null) {
            $patientId = $patient->drive_folder_id;
            if (!$patientId || !$this->checkFolderExists($patientId, $drive)) {
                $rebuildFrom = 'patient';
            }
        }

        // Rebuild only from the first missing level downward
        if ($rebuildFrom !== null) {
            // Root — recreate if it was the missing level
            if ($rebuildFrom === 'root') {
                $rootId = $this->findOrCreateFolder('CliniFlow', null, $drive);
                $connection->update(['drive_root_folder_id' => $rootId]);
                $recreated[] = 'root';
            } else {
                $rootId = $connection->drive_root_folder_id;
            }

            // Professional — recreate if it or any ancestor was missing
            if (in_array($rebuildFrom, ['root', 'doctor'], true)) {
                $doctorId = $this->findOrCreateFolder($doctor->name, $rootId, $drive);
                $doctor->clinics()->updateExistingPivot($clinic->id, [
                    'drive_doctor_folder_id' => $doctorId,
                ]);
                $recreated[] = 'doctor';
            } else {
                $doctorId = $pivot?->drive_doctor_folder_id;
            }

            // Patient — always recreated once any ancestor is missing
            $patientId = $this->findOrCreateFolder($patientName, $doctorId, $drive);
            $patient->update(['drive_folder_id' => $patientId]);
            $patient->refresh();
            $recreated[] = 'patient';
        } else {
            $patientId = $patient->drive_folder_id;
        }

        // Level 4 — Category subfolder (find or create; never duplicates)
        $uploadFolderId = $patientId;
        if ($categoria) {
            $uploadFolderId = $this->findOrCreateFolder($categoria, $patientId, $drive);
        }

        return [
            'upload_folder_id'  => $uploadFolderId,
            'patient_folder_id' => $patientId,
            'recreated_levels'  => $recreated,
        ];
    }

    /**
     * Build folder hierarchy for first-time setup (no cached IDs).
     *
     * @return array{upload_folder_id: string, patient_folder_id: string}
     */
    private function ensureFirstTimeStructure(
        Patient $patient,
        User $doctor,
        ?string $categoria,
        Google_Service_Drive $drive
    ): array {
        $clinic      = $patient->clinic;
        $connection  = $clinic->storageConnection;
        $patientName = trim("{$patient->nome} {$patient->sobrenome}");

        $rootId = $this->findOrCreateFolder('CliniFlow', null, $drive);
        $connection->update(['drive_root_folder_id' => $rootId]);

        $doctorFolder = $this->findOrCreateFolder($doctor->name, $rootId, $drive);
        $doctor->clinics()->updateExistingPivot($clinic->id, [
            'drive_doctor_folder_id' => $doctorFolder,
        ]);

        $patientFolder = $this->findOrCreateFolder($patientName, $doctorFolder, $drive);
        $patient->update(['drive_folder_id' => $patientFolder]);

        $uploadFolderId = $categoria
            ? $this->findOrCreateFolder($categoria, $patientFolder, $drive)
            : $patientFolder;

        return [
            'upload_folder_id'  => $uploadFolderId,
            'patient_folder_id' => $patientFolder,
        ];
    }

    /**
     * Use validated cached folder IDs and ensure category subfolder exists.
     *
     * @return array{upload_folder_id: string, patient_folder_id: string}
     */
    private function resolveExistingStructure(
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
            ? $this->findOrCreateFolder($categoria, $patientFolder, $drive)
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
        $drive  = $this->getDriveForClinic($patient->clinic);
        $this->logDisasterRecoveryAuthorized($patient, $doctor);
        $result = $this->rebuildAuthorizedStructure($patient, $doctor, null, $drive);
        $this->logDisasterRecoveryCompleted($patient, $result['recreated_levels']);
    }

    /**
     * Authorized disaster recovery — rebuilds only the missing levels of the hierarchy.
     *
     * @return array{upload_folder_id: string, patient_folder_id: string, recreated_levels: string[]}
     */
    private function rebuildAuthorizedStructure(
        Patient $patient,
        User $doctor,
        ?string $categoria,
        Google_Service_Drive $drive
    ): array {
        return $this->partialRepair($patient, $doctor, $categoria, $drive);
    }

    private function logDisasterRecoveryAuthorized(Patient $patient, User $doctor): void
    {
        DriveActivityLog::create([
            'clinic_id'   => $patient->clinic_id,
            'patient_id'  => $patient->id,
            'event_type'  => 'structure_recovery_authorized',
            'description' => 'Usuário autorizou a recriação da estrutura.',
            'metadata'    => ['authorized_by_id' => $doctor->id],
        ]);
    }

    private function logDisasterRecoveryCompleted(Patient $patient, array $recreatedLevels = []): void
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

    /**
     * Level 3 — patient folder inside the doctor's folder.
     */
    public function ensurePatientFolder(Patient $patient, User $doctor): string
    {
        $drive = $this->getDriveForClinic($patient->clinic);

        if ($this->structureWasPreviouslyEstablished($patient, $doctor)) {
            $this->assertStructureAvailable($patient, $doctor);
            return $this->resolveExistingStructure($patient, $doctor, null, $drive)['patient_folder_id'];
        }

        return $this->ensureFirstTimeStructure($patient, $doctor, null, $drive)['patient_folder_id'];
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
        $drive = $this->getDriveForClinic($patient->clinic);

        if ($this->structureWasPreviouslyEstablished($patient, $doctor)) {
            $this->assertStructureAvailable($patient, $doctor);

            return $this->resolveExistingStructure($patient, $doctor, $categoria, $drive);
        }

        return $this->ensureFirstTimeStructure($patient, $doctor, $categoria, $drive);
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
                $this->logStructureNotFound($patient, 'upload', $folderId);
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
                $this->assertStructureAvailable($patient, $doctor);
                $resolved = $this->resolveExistingStructure($patient, $doctor, $categoria, $drive);
            } elseif ($authorizeRecovery) {
                $this->logDisasterRecoveryAuthorized($patient, $doctor);
                $repaired  = $this->rebuildAuthorizedStructure($patient, $doctor, $categoria, $drive);
                $resolved  = [
                    'upload_folder_id'  => $repaired['upload_folder_id'],
                    'patient_folder_id' => $repaired['patient_folder_id'],
                ];
                $recreated = true;
                $this->logDisasterRecoveryCompleted($patient, $repaired['recreated_levels']);
            } else {
                $resolved = $this->ensureFirstTimeStructure($patient, $doctor, $categoria, $drive);
            }

            $folderId = $resolved['upload_folder_id'];
        }

        try {
            $uploaded = $this->performDriveUpload($drive, $folderId, $filePath, $fileName, $mimeType);
        } catch (Google_Service_Exception $e) {
            if ($e->getCode() === 404 && !$authorizeRecovery) {
                $this->logStructureNotFound($patient, 'upload', $folderId);
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

            $newDriveFolderId = $this->findOrCreateFolder(
                $newCategoria,
                $photo->patient->drive_folder_id,
                $drive
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

        $drive  = $this->getDriveForClinic($patient->clinic);
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
            if ($this->findFolder($categoria, $patientFolderId, $drive) === null) {
                $this->createFolder($categoria, $patientFolderId, $drive);
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

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function listPhotos(Patient $patient)
    {
        return $patient->photos()->latest()->get();
    }
}
