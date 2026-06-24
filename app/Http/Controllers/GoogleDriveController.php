<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\DriveActivityLog;
use App\Models\Patient;
use App\Models\PatientPhoto;
use App\Models\User;
use App\Services\GoogleDriveService;
use Google_Service_Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class GoogleDriveController extends Controller
{
    // ─── OAuth ────────────────────────────────────────────────────────────────

    public function connect(GoogleDriveService $driveService)
    {
        return redirect($driveService->getAuthUrl());
    }

    public function callback(Request $request, GoogleDriveService $driveService)
    {
        /** @var User $user */
        $user   = Auth::user();
        $clinic = $user->currentClinic();

        if (!$clinic) {
            return redirect()->route('dashboard')->with('error', 'Nenhuma clínica ativa.');
        }

        $token = $driveService->exchangeCode($request->get('code'));

        if (isset($token['error'])) {
            return redirect()->route('dashboard')
                ->with('error', 'Erro na autenticação com Google: ' . ($token['error_description'] ?? $token['error']));
        }

        $googleEmail = $driveService->fetchEmailFromToken($token);

        $clinic->storageConnection()->updateOrCreate(
            ['clinic_id' => $clinic->id],
            [
                'provider'      => 'google',
                'google_email'  => $googleEmail,
                'refresh_token' => Crypt::encryptString($token['refresh_token'] ?? ''),
                'access_token'  => Crypt::encryptString(json_encode($token)),
                'expires_at'    => isset($token['expires_in'])
                    ? now()->addSeconds($token['expires_in'])
                    : null,
                'status'        => 'connected',
            ]
        );

        $clinic->update(['google_connected_at' => now()]);

        $emailLabel = $googleEmail ? " ({$googleEmail})" : '';

        return redirect()->route('patients.index')
            ->with('success', "Google Drive conectado com sucesso{$emailLabel}!");
    }

    public function disconnect(Clinic $clinic)
    {
        $clinic->storageConnection()->delete();
        $clinic->update(['google_connected_at' => null]);

        return back()->with('success', 'Google Drive desconectado.');
    }

    public function confirmDisclaimer()
    {
        /** @var User $user */
        $user   = Auth::user();
        $clinic = $user->currentClinic();

        if ($clinic && !$clinic->storage_disclaimer_confirmed_at) {
            $clinic->update(['storage_disclaimer_confirmed_at' => now()]);
        }

        return back();
    }

    // ─── Photos ───────────────────────────────────────────────────────────────

    public function uploadPhoto(Request $request, Patient $patient, GoogleDriveService $driveService)
    {
        $request->validate([
            'photo'        => 'required|file|image|max:10240',
            'categoria'    => 'required|string|max:100',
            'subcategoria' => 'required|string|max:100',
            'dente'        => 'nullable|string|max:10',
            'taken_at'     => 'nullable|date',
        ]);

        $file = $request->file('photo');
        /** @var User $doctor */
        $doctor = Auth::user();

        try {
            $driveService->uploadPhoto(
                $patient,
                $doctor,
                $file->getRealPath(),
                $file->getClientOriginalName(),
                $file->getMimeType(),
                [
                    'categoria'      => $request->categoria,
                    'subcategoria'   => $request->subcategoria,
                    'dente'          => $request->dente,
                    'taken_at'       => $request->taken_at,
                    'uploaded_by_id' => $doctor->id,
                ]
            );

            return back()->with('success', 'Foto de ' . $patient->nome_completo . ' enviada para o Google Drive da clínica.');
        } catch (\Exception $e) {
            return back()->with('error', 'Falha ao enviar foto para o Google Drive: ' . $e->getMessage());
        }
    }

    /**
     * Proxy — serve a private Drive photo through the server.
     *
     * Files stored with DRIVE_FILE scope cannot be loaded directly by the browser.
     * This endpoint authenticates against the clinic's Drive account and streams
     * the image bytes. When a file is not found (404), marks it as missing in DB
     * and logs the event.
     */
    public function viewPhoto(Patient $patient, PatientPhoto $photo, GoogleDriveService $driveService)
    {
        abort_if($photo->patient_id !== $patient->id, 404);

        /** @var User $user */
        $user  = Auth::user();
        $clinic = $patient->clinic;
        abort_unless(
            $user->clinics()->where('clinics.id', $clinic->id)->exists(),
            403
        );

        try {
            return $driveService->streamPhoto($photo);
        } catch (Google_Service_Exception $e) {
            if ($e->getCode() === 404) {
                if ($photo->status !== 'missing') {
                    $photo->update(['status' => 'missing']);

                    DriveActivityLog::create([
                        'clinic_id'   => $photo->clinic_id,
                        'patient_id'  => $photo->patient_id,
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

                abort(404, 'Arquivo não encontrado no Google Drive.');
            }

            Log::error('[viewPhoto] Erro do Google Drive', [
                'photo_id' => $photo->id,
                'code'     => $e->getCode(),
                'error'    => $e->getMessage(),
            ]);
            abort(502, 'Não foi possível carregar a imagem do Google Drive.');
        } catch (\Throwable $e) {
            Log::error('[viewPhoto] Falha ao carregar imagem do Drive', [
                'photo_id' => $photo->id,
                'error'    => $e->getMessage(),
            ]);
            abort(502, 'Não foi possível carregar a imagem do Google Drive.');
        }
    }

    // ─── Integrity ────────────────────────────────────────────────────────────

    /**
     * Full integrity check for a patient's photos and folder structure.
     * Called manually via the "Verificar Drive" button.
     */
    public function verifyIntegrity(Patient $patient, GoogleDriveService $driveService)
    {
        /** @var User $doctor */
        $doctor = Auth::user();
        $clinic = $patient->clinic;

        if (!$clinic || !$clinic->storageConnection) {
            return back()->with('error', 'Google Drive não conectado.');
        }

        try {
            $photoStats  = $driveService->verifyPatientPhotos($patient);
            $folderStats = $driveService->verifyFolderStructure($patient, $doctor);
        } catch (\Throwable $e) {
            Log::error('[verifyIntegrity] Falha na verificação do Drive', [
                'patient_id' => $patient->id,
                'error'      => $e->getMessage(),
            ]);
            return back()->with('error', 'Erro ao verificar o Google Drive: ' . $e->getMessage());
        }

        $checked   = $photoStats['checked'];
        $missing   = $photoStats['missing'];
        $recreated = count($folderStats['recreated']);

        $parts = ["Verificação concluída. {$checked} foto(s) verificada(s)."];
        if ($missing > 0)   $parts[] = "{$missing} arquivo(s) não encontrado(s) no Drive.";
        if ($recreated > 0) $parts[] = "{$recreated} pasta(s) recriada(s) automaticamente.";

        $level = ($missing > 0 || $recreated > 0) ? 'error' : 'success';

        return back()->with($level, implode(' ', $parts));
    }
}
