<?php

namespace App\Services;

use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use Google_Service_Exception;
use Google_Service_Oauth2;
use Illuminate\Support\Facades\Crypt;
use App\Models\Clinic;
use App\Models\DriveActivityLog;
use App\Models\Patient;
use App\Models\PatientPhoto;
use App\Models\User;

class GoogleDriveService
{
    private Google_Client $client;

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
     * Restores the cached access token and refreshes when expired.
     */
    public function getDriveForClinic(Clinic $clinic): Google_Service_Drive
    {
        $connection = $clinic->storageConnection;

        if (!$connection || !$connection->refresh_token) {
            throw new \RuntimeException('Nenhuma conta Google conectada para esta clínica.');
        }

        if ($connection->access_token) {
            try {
                $cached = json_decode(Crypt::decryptString($connection->access_token), true);
                $this->client->setAccessToken($cached);
            } catch (\Throwable) {
                // Corrupted cache — will refresh below
            }
        }

        if ($this->client->isAccessTokenExpired()) {
            $refreshToken = Crypt::decryptString($connection->refresh_token);
            $newToken     = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);

            if (isset($newToken['error'])) {
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

        return new Google_Service_Drive($this->client);
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

        $result = $drive->files->listFiles([
            'q'        => $q,
            'fields'   => 'files(id)',
            'spaces'   => 'drive',
            'pageSize' => 1,
        ]);

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

        $folder = $drive->files->create($metadata, ['fields' => 'id']);

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

    /**
     * Check whether a folder ID still exists and is not trashed.
     */
    private function checkFolderExists(string $folderId, Google_Service_Drive $drive): bool
    {
        try {
            $file = $drive->files->get($folderId, ['fields' => 'id,trashed']);
            return !(bool) $file->getTrashed();
        } catch (Google_Service_Exception $e) {
            if ($e->getCode() === 404) return false;
            throw $e;
        }
    }

    // ─── Folder hierarchy ─────────────────────────────────────────────────────

    /**
     * Level 1 — "CliniFlow" root folder in the clinic's Drive.
     * ID cached in clinic_storage_connections.drive_root_folder_id.
     */
    private function ensureRootFolder(Clinic $clinic, Google_Service_Drive $drive): string
    {
        $connection = $clinic->storageConnection;

        if ($connection->drive_root_folder_id) {
            return $connection->drive_root_folder_id;
        }

        $folderId = $this->findOrCreateFolder('CliniFlow', null, $drive);
        $connection->update(['drive_root_folder_id' => $folderId]);

        return $folderId;
    }

    /**
     * Level 2 — doctor folder inside CliniFlow.
     * ID cached in clinic_user.drive_doctor_folder_id (per doctor × clinic).
     */
    private function ensureDoctorFolder(
        User $doctor,
        string $rootFolderId,
        Clinic $clinic,
        Google_Service_Drive $drive
    ): string {
        $pivot = $doctor->clinics()->where('clinics.id', $clinic->id)->first()?->pivot;

        if ($pivot?->drive_doctor_folder_id) {
            return $pivot->drive_doctor_folder_id;
        }

        $folderId = $this->findOrCreateFolder($doctor->name, $rootFolderId, $drive);

        $doctor->clinics()->updateExistingPivot($clinic->id, [
            'drive_doctor_folder_id' => $folderId,
        ]);

        return $folderId;
    }

    /**
     * Level 3 — patient folder inside the doctor's folder.
     * ID cached in patients.drive_folder_id.
     */
    public function ensurePatientFolder(Patient $patient, User $doctor): string
    {
        if ($patient->drive_folder_id) {
            return $patient->drive_folder_id;
        }

        $clinic         = $patient->clinic;
        $drive          = $this->getDriveForClinic($clinic);
        $rootFolderId   = $this->ensureRootFolder($clinic, $drive);
        $doctorFolderId = $this->ensureDoctorFolder($doctor, $rootFolderId, $clinic, $drive);

        $patientName = trim("{$patient->nome} {$patient->sobrenome}");
        $folderId    = $this->findOrCreateFolder($patientName, $doctorFolderId, $drive);

        $patient->update(['drive_folder_id' => $folderId]);

        return $folderId;
    }

    // ─── Upload ───────────────────────────────────────────────────────────────

    public function uploadPhoto(
        Patient $patient,
        User $doctor,
        string $filePath,
        string $fileName,
        string $mimeType,
        array $metadata = []
    ): PatientPhoto {
        $drive    = $this->getDriveForClinic($patient->clinic);
        $folderId = $this->ensurePatientFolder($patient, $doctor);

        $fileMetadata = new Google_Service_Drive_DriveFile([
            'name'    => $fileName,
            'parents' => [$folderId],
        ]);

        $uploaded = $drive->files->create($fileMetadata, [
            'data'       => file_get_contents($filePath),
            'mimeType'   => $mimeType,
            'uploadType' => 'multipart',
            'fields'     => 'id',
        ]);

        return PatientPhoto::create([
            'clinic_id'       => $patient->clinic_id,
            'patient_id'      => $patient->id,
            'drive_file_id'   => $uploaded->getId(),
            'drive_folder_id' => $folderId,
            'filename'        => $fileName,
            'mime_type'       => $mimeType,
            'taken_at'        => $metadata['taken_at'] ?? now(),
            'categoria'       => $metadata['categoria']     ?? null,
            'subcategoria'    => $metadata['subcategoria']  ?? null,
            'dente'           => $metadata['dente']         ?? null,
            'uploaded_by_id'  => $metadata['uploaded_by_id'] ?? null,
            'status'          => 'active',
        ]);
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
        $response = $drive->files->get($photo->drive_file_id, ['alt' => 'media']);
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
            $about = $drive->about->get(['fields' => 'storageQuota']);
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

    // ─── Integrity verification ───────────────────────────────────────────────

    /**
     * Batch-check all active photos for this patient against Drive.
     * Missing files are marked status=missing and logged.
     *
     * @return array{checked:int, missing:int}
     */
    public function verifyPatientPhotos(Patient $patient): array
    {
        $clinic       = $patient->clinic;
        $activePhotos = $patient->photos()
            ->where('status', 'active')
            ->whereNotNull('drive_file_id')
            ->get();

        if ($activePhotos->isEmpty()) {
            return ['checked' => 0, 'missing' => 0];
        }

        $drive   = $this->getDriveForClinic($clinic);
        $fileIds = $activePhotos->pluck('drive_file_id')->filter()->values()->all();

        // Batch lookup in chunks of 50 to stay within query length limits
        $existingIds = [];
        foreach (array_chunk($fileIds, 50) as $chunk) {
            $clauses = array_map(
                fn($id) => "id = '" . str_replace("'", "\\'", $id) . "'",
                $chunk
            );
            $q = '(' . implode(' or ', $clauses) . ') and trashed = false';

            $result = $drive->files->listFiles([
                'q'        => $q,
                'fields'   => 'files(id)',
                'pageSize' => count($chunk),
            ]);

            foreach ($result->getFiles() as $file) {
                $existingIds[] = $file->getId();
            }
        }

        $existingSet   = array_flip($existingIds);
        $missingPhotos = $activePhotos->filter(fn($p) => !isset($existingSet[$p->drive_file_id]));

        foreach ($missingPhotos as $photo) {
            $photo->update(['status' => 'missing']);

            DriveActivityLog::create([
                'clinic_id'   => $clinic->id,
                'patient_id'  => $patient->id,
                'photo_id'    => $photo->id,
                'event_type'  => 'file_deleted',
                'description' => "Arquivo '{$photo->filename}' não encontrado no Google Drive",
                'metadata'    => [
                    'filename'      => $photo->filename,
                    'drive_file_id' => $photo->drive_file_id,
                    'categoria'     => $photo->categoria,
                    'subcategoria'  => $photo->subcategoria,
                ],
            ]);
        }

        return [
            'checked' => $activePhotos->count(),
            'missing' => $missingPhotos->count(),
        ];
    }

    /**
     * Verify that the folder hierarchy for a patient still exists in Drive.
     * Recreates any missing level and logs each event.
     * Uses cascade logic: if a parent is gone, all children are also gone.
     *
     * @return array{recreated: string[]}
     */
    public function verifyFolderStructure(Patient $patient, User $doctor): array
    {
        $clinic     = $patient->clinic;
        $connection = $clinic?->storageConnection;
        $recreated  = [];

        if (!$connection || !$connection->drive_root_folder_id) {
            return ['recreated' => []]; // nothing set up yet
        }

        $drive       = $this->getDriveForClinic($clinic);
        $patientName = trim("{$patient->nome} {$patient->sobrenome}");
        $rootId      = $connection->drive_root_folder_id;

        // ── Level 1: root "CliniFlow" folder ──────────────────────────────────
        if (!$this->checkFolderExists($rootId, $drive)) {
            DriveActivityLog::create([
                'clinic_id'   => $clinic->id,
                'event_type'  => 'root_folder_deleted',
                'description' => 'Pasta raiz CliniFlow foi removida do Google Drive',
                'metadata'    => ['old_folder_id' => $rootId, 'folder_path' => 'CliniFlow'],
            ]);

            $rootId = $this->createFolder('CliniFlow', null, $drive);
            $connection->update(['drive_root_folder_id' => $rootId]);
            $recreated[] = 'CliniFlow';
            DriveActivityLog::create([
                'clinic_id'   => $clinic->id,
                'event_type'  => 'folder_recreated',
                'description' => 'Pasta raiz CliniFlow recriada automaticamente',
                'metadata'    => ['folder_path' => 'CliniFlow', 'new_folder_id' => $rootId],
            ]);

            // Doctor and patient folders were inside root — recreate them too
            $doctorId = $this->createFolder($doctor->name, $rootId, $drive);
            $doctor->clinics()->updateExistingPivot($clinic->id, ['drive_doctor_folder_id' => $doctorId]);
            $recreated[] = "CliniFlow/{$doctor->name}";
            DriveActivityLog::create([
                'clinic_id'   => $clinic->id,
                'event_type'  => 'folder_recreated',
                'description' => "Pasta '{$doctor->name}' recriada automaticamente",
                'metadata'    => ['folder_path' => "CliniFlow/{$doctor->name}", 'new_folder_id' => $doctorId],
            ]);

            if ($patient->drive_folder_id) {
                $patientId = $this->createFolder($patientName, $doctorId, $drive);
                $patient->update(['drive_folder_id' => $patientId]);
                $recreated[] = "CliniFlow/{$doctor->name}/{$patientName}";
                DriveActivityLog::create([
                    'clinic_id'   => $clinic->id,
                    'patient_id'  => $patient->id,
                    'event_type'  => 'folder_recreated',
                    'description' => "Pasta do paciente '{$patientName}' recriada automaticamente",
                    'metadata'    => ['folder_path' => "CliniFlow/{$doctor->name}/{$patientName}", 'new_folder_id' => $patientId],
                ]);
            }

            return ['recreated' => $recreated];
        }

        // ── Level 2: doctor folder ────────────────────────────────────────────
        $pivot        = $doctor->clinics()->where('clinics.id', $clinic->id)->first()?->pivot;
        $doctorFolder = $pivot?->drive_doctor_folder_id;

        if ($doctorFolder && !$this->checkFolderExists($doctorFolder, $drive)) {
            DriveActivityLog::create([
                'clinic_id'   => $clinic->id,
                'event_type'  => 'clinic_folder_deleted',
                'description' => "Pasta '{$doctor->name}' foi removida do Google Drive",
                'metadata'    => ['old_folder_id' => $doctorFolder, 'folder_path' => "CliniFlow/{$doctor->name}"],
            ]);

            $doctorFolder = $this->createFolder($doctor->name, $rootId, $drive);
            $doctor->clinics()->updateExistingPivot($clinic->id, ['drive_doctor_folder_id' => $doctorFolder]);
            $recreated[] = "CliniFlow/{$doctor->name}";
            DriveActivityLog::create([
                'clinic_id'   => $clinic->id,
                'event_type'  => 'folder_recreated',
                'description' => "Pasta '{$doctor->name}' recriada automaticamente",
                'metadata'    => ['folder_path' => "CliniFlow/{$doctor->name}", 'new_folder_id' => $doctorFolder],
            ]);

            if ($patient->drive_folder_id) {
                $patientId = $this->createFolder($patientName, $doctorFolder, $drive);
                $patient->update(['drive_folder_id' => $patientId]);
                $recreated[] = "CliniFlow/{$doctor->name}/{$patientName}";
                DriveActivityLog::create([
                    'clinic_id'   => $clinic->id,
                    'patient_id'  => $patient->id,
                    'event_type'  => 'folder_recreated',
                    'description' => "Pasta do paciente '{$patientName}' recriada automaticamente",
                    'metadata'    => ['folder_path' => "CliniFlow/{$doctor->name}/{$patientName}", 'new_folder_id' => $patientId],
                ]);
            }

            return ['recreated' => $recreated];
        }

        // ── Level 3: patient folder ───────────────────────────────────────────
        if ($patient->drive_folder_id && !$this->checkFolderExists($patient->drive_folder_id, $drive)) {
            DriveActivityLog::create([
                'clinic_id'   => $clinic->id,
                'patient_id'  => $patient->id,
                'event_type'  => 'patient_folder_deleted',
                'description' => "Pasta do paciente '{$patientName}' foi removida do Google Drive",
                'metadata'    => [
                    'old_folder_id' => $patient->drive_folder_id,
                    'folder_path'   => "CliniFlow/{$doctor->name}/{$patientName}",
                ],
            ]);

            $patientId = $this->createFolder($patientName, $doctorFolder ?? $rootId, $drive);
            $patient->update(['drive_folder_id' => $patientId]);
            $recreated[] = "CliniFlow/{$doctor->name}/{$patientName}";
            DriveActivityLog::create([
                'clinic_id'   => $clinic->id,
                'patient_id'  => $patient->id,
                'event_type'  => 'folder_recreated',
                'description' => "Pasta do paciente '{$patientName}' recriada automaticamente",
                'metadata'    => ['folder_path' => "CliniFlow/{$doctor->name}/{$patientName}", 'new_folder_id' => $patientId],
            ]);
        }

        return ['recreated' => $recreated];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function listPhotos(Patient $patient)
    {
        return $patient->photos()->latest()->get();
    }
}
