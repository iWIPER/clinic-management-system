<?php

namespace App\Services;

use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use Illuminate\Support\Facades\Crypt;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\PatientPhoto;

class GoogleDriveService
{
    protected $client;
    protected $drive;

    public function __construct()
    {
        $this->client = new Google_Client();
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setRedirectUri(config('services.google.redirect'));
        $this->client->addScope(Google_Service_Drive::DRIVE_FILE);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
    }

    public function getAuthUrl()
    {
        return $this->client->createAuthUrl();
    }

    public function authenticate($code)
    {
        $token = $this->client->fetchAccessTokenWithAuthCode($code);
        return $token;
    }

    public function setAccessToken($token)
    {
        $this->client->setAccessToken($token);
    }

    public function getClient()
    {
        return $this->client;
    }

    /**
     * Get or refresh token for a clinic.
     */
    public function getDriveForClinic(Clinic $clinic)
    {
        $connection = $clinic->storageConnection;

        if (!$connection || !$connection->refresh_token) {
            throw new \Exception('Google Drive não conectado para esta clínica.');
        }

        $refreshToken = Crypt::decryptString($connection->refresh_token);
        $this->client->refreshToken($refreshToken);

        // Update access token if needed
        if ($this->client->isAccessTokenExpired()) {
            $newToken = $this->client->getAccessToken();
            $connection->update([
                'access_token' => Crypt::encryptString(json_encode($newToken)),
                'expires_at' => now()->addSeconds($newToken['expires_in'] ?? 3600),
            ]);
        }

        $this->drive = new Google_Service_Drive($this->client);
        return $this->drive;
    }

    /**
     * Create patient folder if not exists.
     */
    public function ensurePatientFolder(Patient $patient)
    {
        if ($patient->drive_folder_id) {
            return $patient->drive_folder_id;
        }

        $drive = $this->getDriveForClinic($patient->clinic);

        $folderName = 'Paciente - ' . $patient->nome . ' ' . $patient->sobrenome;

        $fileMetadata = new Google_Service_Drive_DriveFile([
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);

        $folder = $drive->files->create($fileMetadata, ['fields' => 'id']);
        $folderId = $folder->id;

        $patient->update(['drive_folder_id' => $folderId]);

        return $folderId;
    }

    /**
     * Upload a photo to patient's Drive folder.
     */
    public function uploadPhoto(Patient $patient, $filePath, $fileName, $mimeType, $metadata = [])
    {
        $drive = $this->getDriveForClinic($patient->clinic);
        $folderId = $this->ensurePatientFolder($patient);

        $fileMetadata = new Google_Service_Drive_DriveFile([
            'name' => $fileName,
            'parents' => [$folderId],
        ]);

        $content = file_get_contents($filePath);

        $uploadedFile = $drive->files->create($fileMetadata, [
            'data' => $content,
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'fields' => 'id,thumbnailLink,webViewLink',
        ]);

        // Save to DB
        $photo = PatientPhoto::create([
            'clinic_id' => $patient->clinic_id,
            'patient_id' => $patient->id,
            'drive_file_id' => $uploadedFile->id,
            'drive_folder_id' => $folderId,
            'filename' => $fileName,
            'mime_type' => $mimeType,
            'taken_at' => $metadata['taken_at'] ?? now(),
            'categoria' => $metadata['categoria'] ?? null,
            'dente' => $metadata['dente'] ?? null,
            'uploaded_by_id' => $metadata['uploaded_by_id'] ?? null,
        ]);

        return $photo;
    }

    public function getPhotoThumbnail($fileId)
    {
        // Return thumbnail URL or use API
        return "https://drive.google.com/thumbnail?id={$fileId}&sz=w400";
    }

    public function listPhotos(Patient $patient)
    {
        return $patient->photos()->latest()->get();
    }
}
