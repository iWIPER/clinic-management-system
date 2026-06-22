<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Auth;

class GoogleDriveController extends Controller
{
    public function connect(GoogleDriveService $driveService)
    {
        return redirect($driveService->getAuthUrl());
    }

    public function callback(Request $request, GoogleDriveService $driveService)
    {
        $clinic = Auth::user()->currentClinic();

        if (!$clinic) {
            return redirect()->route('dashboard')->with('error', 'Nenhuma clínica ativa.');
        }

        $token = $driveService->authenticate($request->get('code'));

        // Save encrypted refresh token
        $clinic->storageConnection()->updateOrCreate(
            ['clinic_id' => $clinic->id],
            [
                'provider' => 'google',
                'refresh_token' => Crypt::encryptString($token['refresh_token']),
                'access_token' => isset($token['access_token']) ? Crypt::encryptString($token['access_token']) : null,
                'expires_at' => isset($token['expires_in']) ? now()->addSeconds($token['expires_in']) : null,
                'status' => 'connected',
            ]
        );

        $clinic->update(['google_connected_at' => now()]);

        return redirect()->route('patients.index')->with('success', 'Google Drive conectado com sucesso!');
    }

    public function disconnect(Clinic $clinic)
    {
        $clinic->storageConnection()->delete();
        $clinic->update(['google_connected_at' => null]);

        return back()->with('success', 'Google Drive desconectado.');
    }

    // Upload photo from patient form (example)
    public function uploadPhoto(Request $request, Patient $patient, GoogleDriveService $driveService)
    {
        $request->validate([
            'photo' => 'required|file|image|max:10240', // 10MB
            'categoria' => 'nullable|string',
            'dente' => 'nullable|string',
            'taken_at' => 'nullable|date',
        ]);

        $file = $request->file('photo');
        $path = $file->getRealPath();
        $fileName = $file->getClientOriginalName();
        $mime = $file->getMimeType();

        try {
            $photo = $driveService->uploadPhoto($patient, $path, $fileName, $mime, [
                'categoria' => $request->categoria,
                'dente' => $request->dente,
                'taken_at' => $request->taken_at,
                'uploaded_by_id' => Auth::id(),
            ]);

            return back()->with('success', 'Foto enviada para o Google Drive do paciente!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao enviar foto: ' . $e->getMessage());
        }
    }
}
