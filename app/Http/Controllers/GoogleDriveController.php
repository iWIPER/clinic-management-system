<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\PatientPhoto;
use App\Models\User;
use App\Exceptions\DriveStructureMissingException;
use App\Exceptions\GoogleDriveReauthRequiredException;
use App\Services\GoogleDriveHealthCheckService;
use App\Services\GoogleDriveService;
use Google_Service_Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class GoogleDriveController extends Controller
{
    // ─── OAuth ────────────────────────────────────────────────────────────────

    /**
     * "Reconectar Drive" — first try to renew silently with the stored refresh
     * token; only fall back to the Google consent screen if that fails.
     *
     * Uses Inertia::location() rather than a plain redirect(): when this route
     * is hit via an Inertia visit (fetch/XHR, e.g. the modal's <Link>), a raw
     * 302 to accounts.google.com is followed by the browser's fetch and gets
     * silently blocked by CORS — the click appears to do nothing. Inertia::location()
     * replies with a 409 + X-Inertia-Location header, which makes the Inertia
     * client perform a real window.location navigation instead.
     */
    public function connect(GoogleDriveService $driveService)
    {
        /** @var User $user */
        $user   = Auth::user();
        $clinic = $user->currentClinic();

        if ($clinic && $driveService->tryRenewConnection($clinic)) {
            return back()->with('success', 'Conexão com o Google Drive renovada automaticamente.');
        }

        return Inertia::location($driveService->getAuthUrl());
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
        $connection  = $clinic->storageConnection;

        // Google only returns refresh_token when it issues a new one (e.g. first
        // consent, or a forced prompt=consent screen). When it's absent, keep the
        // previously stored refresh_token instead of overwriting it with an empty
        // value — otherwise every reconnect risks silently destroying a still-valid
        // refresh token and permanently locking the clinic out of Drive.
        if (empty($token['refresh_token']) && empty($connection?->refresh_token)) {
            return redirect()->route('dashboard')->with('error',
                'O Google não retornou permissão de acesso offline. Tente reconectar e aceite o acesso solicitado.');
        }

        $updates = [
            'provider'     => 'google',
            'google_email' => $googleEmail,
            'access_token' => Crypt::encryptString(json_encode($token)),
            'expires_at'   => isset($token['expires_in'])
                ? now()->addSeconds($token['expires_in'])
                : null,
            'status'       => 'connected',
        ];

        if (!empty($token['refresh_token'])) {
            $updates['refresh_token'] = Crypt::encryptString($token['refresh_token']);
        }

        $clinic->storageConnection()->updateOrCreate(['clinic_id' => $clinic->id], $updates);

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
            'photo'                       => 'required_unless:authorize_structure_recovery,true|file|image|max:10240',
            'categoria'                   => 'required|string|max:100',
            'subcategoria'                => 'required|string|max:100',
            'dente'                       => 'nullable|string|max:10',
            'taken_at'                    => 'nullable|date',
            'authorize_structure_recovery' => 'sometimes|boolean',
        ]);

        /** @var User $doctor */
        $doctor = Auth::user();
        $authorizeRecovery = $request->boolean('authorize_structure_recovery');

        if (!$authorizeRecovery && !$request->hasFile('photo')) {
            return back()->with('error', 'Selecione um arquivo para enviar.');
        }

        $file = $request->file('photo');

        try {
            if ($authorizeRecovery && !$file) {
                return back()->with('error', 'Não foi possível retomar o upload. Selecione o arquivo novamente.');
            }

            $extension   = $file->getClientOriginalExtension();
            $logicalName = $request->dente
                ? "{$request->subcategoria} - Dente {$request->dente}.{$extension}"
                : "{$request->subcategoria}.{$extension}";

            $result = $driveService->uploadPhoto(
                $patient,
                $doctor,
                $file->getRealPath(),
                $logicalName,
                $file->getMimeType(),
                [
                    'categoria'      => $request->categoria,
                    'subcategoria'   => $request->subcategoria,
                    'dente'          => $request->dente,
                    'taken_at'       => $request->taken_at,
                    'uploaded_by_id' => $doctor->id,
                ],
                $authorizeRecovery
            );

            $message = $result['structure_recreated']
                ? '✓ Estrutura recriada com sucesso. Uma nova estrutura foi criada no Google Drive. O upload foi concluído normalmente.'
                : 'Foto de ' . $patient->nome_completo . ' enviada para o Google Drive da clínica.';

            return back()->with('success', $message);
        } catch (DriveStructureMissingException) {
            return back()
                ->with('disaster_recovery_required', true)
                ->withInput($request->except('photo'));
        } catch (GoogleDriveReauthRequiredException) {
            return Inertia::location($driveService->getAuthUrl());
        } catch (\Throwable $e) {
            Log::error('[uploadPhoto] Falha no upload para o Google Drive', [
                'patient_id' => $patient->id,
                'error'      => $e->getMessage(),
            ]);

            return back()->with('error', 'Não foi possível enviar o arquivo. Tente novamente em instantes.');
        }
    }

    /**
     * Rename a photo within the 2-hour editing window.
     */
    public function renamePhoto(Request $request, Patient $patient, PatientPhoto $photo, GoogleDriveService $driveService)
    {
        abort_if($photo->patient_id !== $patient->id, 404);

        if ($photo->created_at->lt(now()->subHours(2))) {
            return back()->with('error', 'Este documento passou a integrar o histórico clínico e não pode mais ser renomeado.');
        }

        $request->validate([
            'new_name'    => 'required|string|max:200',
            'categoria'   => 'nullable|string|max:100',
            'dente'       => 'nullable|string|max:10',
            'description' => 'nullable|string|max:500',
            'observacao'  => 'nullable|string',
        ]);

        try {
            $driveService->renamePhoto(
                $photo,
                trim($request->new_name),
                $request->dente ? trim($request->dente) : null,
                $request->categoria ? trim($request->categoria) : null,
                $request->description ? trim($request->description) : null,
                $request->observacao ? trim($request->observacao) : null,
                $request->user()
            );
            return back()->with('success', 'Documento atualizado com sucesso.');
        } catch (GoogleDriveReauthRequiredException) {
            return Inertia::location($driveService->getAuthUrl());
        } catch (\Throwable $e) {
            Log::error('[renamePhoto] Falha ao atualizar arquivo no Drive', [
                'photo_id' => $photo->id,
                'error'    => $e->getMessage(),
            ]);
            return back()->with('error', 'Não foi possível atualizar o documento. Tente novamente.');
        }
    }

    /**
     * Permanently delete a photo within the 2-hour editing window.
     * Hard-deletes the record and removes the file from Drive.
     */
    public function deletePhoto(Request $request, Patient $patient, PatientPhoto $photo, GoogleDriveService $driveService)
    {
        abort_if($photo->patient_id !== $patient->id, 404);

        if ($photo->created_at->lt(now()->subHours(2))) {
            return back()->with('error', 'Este documento passou a integrar o histórico clínico e não pode mais ser removido.');
        }

        try {
            $driveService->deletePhotoFromSystem($photo, $request->user());
            return back()->with('success', 'Documento removido permanentemente.');
        } catch (GoogleDriveReauthRequiredException) {
            return Inertia::location($driveService->getAuthUrl());
        } catch (\Throwable $e) {
            Log::error('[deletePhoto] Falha ao remover arquivo do Drive', [
                'photo_id' => $photo->id,
                'error'    => $e->getMessage(),
            ]);
            return back()->with('error', 'Não foi possível remover o documento. Tente novamente.');
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
                $driveService->markPhotoAsRemoved($photo);
                abort(404);
            }

            Log::error('[viewPhoto] Erro do Google Drive', [
                'photo_id' => $photo->id,
                'code'     => $e->getCode(),
                'error'    => $e->getMessage(),
            ]);
            abort(404);
        } catch (\Throwable $e) {
            Log::error('[viewPhoto] Falha ao carregar imagem do Drive', [
                'photo_id' => $photo->id,
                'error'    => $e->getMessage(),
            ]);
            abort(404);
        }
    }

    /**
     * Authorized disaster recovery — recreates folder structure without upload.
     */
    public function recoverStructure(Patient $patient, GoogleDriveService $driveService)
    {
        /** @var User $doctor */
        $doctor = Auth::user();

        try {
            $driveService->recoverStructure($patient, $doctor);

            return back()->with(
                'success',
                '✓ Estrutura recriada com sucesso. Uma nova estrutura foi criada no Google Drive.'
            );
        } catch (GoogleDriveReauthRequiredException) {
            return Inertia::location($driveService->getAuthUrl());
        } catch (\Throwable $e) {
            Log::error('[recoverStructure] Falha na recuperação do Drive', [
                'patient_id' => $patient->id,
                'error'      => $e->getMessage(),
            ]);

            return back()->with('error', 'Não foi possível recriar a estrutura. Tente novamente em instantes.');
        }
    }

    // ─── Health Check ─────────────────────────────────────────────────────────

    /**
     * Full Drive health check — returns a structured JSON integrity report.
     */
    public function healthCheck(Patient $patient, GoogleDriveHealthCheckService $healthCheck)
    {
        /** @var User $doctor */
        $doctor = Auth::user();

        abort_unless(
            $doctor->clinics()->where('clinics.id', $patient->clinic_id)->exists(),
            403
        );

        try {
            $report = $healthCheck->run($patient, $doctor);

            return response()->json($report);
        } catch (\Throwable $e) {
            Log::error('[healthCheck] Falha na verificação do Drive', [
                'patient_id' => $patient->id,
                'error'      => $e->getMessage(),
            ]);

            return response()->json(
                $healthCheck->buildFailureReport($patient, $doctor, $e)
            );
        }
    }
}
