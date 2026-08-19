<?php

namespace App\Http\Controllers;

use App\Exceptions\DriveStructureMissingException;
use App\Exceptions\GoogleDriveReauthRequiredException;
use App\Jobs\UploadEvolutionPhotoJob;
use App\Models\ClinicalEvolution;
use App\Models\Patient;
use App\Models\PatientPhoto;
use App\Models\User;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PatientEvolutionController extends Controller
{
    public function store(Request $request, Patient $patient, GoogleDriveService $driveService)
    {
        $validated = $request->validate([
            'professional_id'    => ['required', \Illuminate\Validation\Rule::exists('clinic_user', 'user_id')->where('clinic_id', $patient->clinic_id)],
            'recorded_at'        => 'required|date',
            'content'            => 'required|string',
            'signature_required' => 'sometimes|boolean',
            'photos'             => 'nullable|array|max:5',
            'photos.*'           => 'file|image|max:10240',
        ]);

        // Salva a evolução primeiro — a nota clínica nunca deve ser perdida
        // por causa de um problema no envio de fotos pro Drive.
        $evolution = ClinicalEvolution::create([
            'clinic_id'          => $patient->clinic_id,
            'patient_id'         => $patient->id,
            'professional_id'    => $validated['professional_id'],
            'content'            => $validated['content'],
            'signature_required' => $validated['signature_required'] ?? false,
            'recorded_at'        => $validated['recorded_at'],
        ]);

        $pendingCount = 0;
        $preflightFailureReason = null;

        if ($request->hasFile('photos')) {
            /** @var User $uploader */
            $uploader = Auth::user();

            // Resolve a pasta "Evoluções" do paciente UMA vez, de forma
            // síncrona e rápida (só checagem de estrutura — nenhum arquivo é
            // enviado aqui). O envio de cada foto de fato acontece em segundo
            // plano (UploadEvolutionPhotoJob), pra não segurar a resposta
            // esperando o Drive — subir 5 fotos síncronas era a principal
            // causa de lentidão ao criar uma evolução com fotos.
            $preResolvedFolderId = null;

            try {
                $resolved = $driveService->resolveUploadFolder($patient, $uploader, 'Evoluções');
                $preResolvedFolderId = $resolved['upload_folder_id'];
            } catch (GoogleDriveReauthRequiredException) {
                // Diferente do fluxo de retry síncrono (que redireciona pro
                // OAuth do Google): aqui a evolução já foi salva e não faz
                // sentido sequestrar a resposta com um redirect. Em vez
                // disso, as fotos ficam pendentes com o motivo certo — o
                // usuário reconecta o Drive pelo botão na tela quando quiser.
                $preflightFailureReason = 'drive_reauth_required';
            } catch (DriveStructureMissingException) {
                $preflightFailureReason = 'drive_structure_missing';
            }

            foreach ($request->file('photos') as $index => $file) {
                $logicalName = 'Evolução ' . $evolution->recorded_at->format('d-m-Y') . ' - ' . ($index + 1) . '.' . $file->getClientOriginalExtension();

                $photo = $this->storeLocalPhoto(
                    $patient,
                    $evolution,
                    $file,
                    $logicalName,
                    $uploader->id,
                    $preflightFailureReason ? 'pending' : 'uploading',
                    $preflightFailureReason
                );

                if ($preflightFailureReason) {
                    $pendingCount++;
                    continue;
                }

                UploadEvolutionPhotoJob::dispatch($photo->id, $preResolvedFolderId);
            }
        }

        if ($pendingCount > 0) {
            $plural = $pendingCount > 1;
            $reasonMsg = match ($preflightFailureReason) {
                'drive_reauth_required'   => 'a conexão com o Google Drive precisa ser renovada',
                'drive_structure_missing' => 'a estrutura de pastas do paciente no Drive não foi encontrada',
                default                   => 'houve uma falha no envio',
            };

            return back()->with('error', "Evolução registrada. {$pendingCount} foto" . ($plural ? 's ficaram pendentes' : ' ficou pendente') . " — {$reasonMsg}.");
        }

        return back()->with('success', 'Evolução registrada.');
    }

    // Salva o arquivo localmente (pra nunca pedir de novo ao usuário, mesmo
    // se falhar) e cria a linha PatientPhoto — como 'uploading' (job vai
    // atualizar essa mesma linha) ou já como 'pending' com o motivo certo
    // (quando a falha já é conhecida antes de tentar, ver caller acima).
    // Quando o motivo é 'drive_structure_missing'/'drive_reauth_required', o
    // front-end mostra o aviso + botão específico direto, em vez do
    // "Reenviar" genérico (ver EvolutionDetailModal.vue).
    private function storeLocalPhoto(
        Patient $patient,
        ClinicalEvolution $evolution,
        UploadedFile $file,
        string $logicalName,
        int $uploaderId,
        string $status,
        ?string $failureReason
    ): PatientPhoto {
        $localPath = $file->store('pending-evolution-photos/' . $patient->id, 'local');

        return PatientPhoto::create([
            'clinic_id'             => $patient->clinic_id,
            'patient_id'            => $patient->id,
            'clinical_evolution_id' => $evolution->id,
            'filename'              => $logicalName,
            'mime_type'             => $file->getMimeType(),
            'taken_at'              => $evolution->recorded_at,
            'categoria'             => 'Evoluções',
            'status'                => $status,
            'failure_reason'        => $failureReason,
            'local_path'            => $localPath,
            'uploaded_by_id'        => $uploaderId,
        ]);
    }

    // Reenvia uma foto que ficou pendente (falhou no upload original pro
    // Drive). Reaproveita o mesmo arquivo já guardado localmente — o usuário
    // não precisa anexar de novo. Ao ter sucesso, o registro pendente é
    // substituído pelo novo (criado por uploadPhoto(), já com drive_file_id).
    public function retryPhoto(Request $request, Patient $patient, PatientPhoto $photo, GoogleDriveService $driveService)
    {
        abort_unless($photo->patient_id === $patient->id, 404);

        if ($photo->status !== 'pending' || ! $photo->local_path) {
            return back()->with('error', 'Esta foto não está pendente de envio.');
        }

        $localFullPath = Storage::disk('local')->path($photo->local_path);

        if (! is_file($localFullPath)) {
            return back()->with('error', 'Arquivo local não encontrado — anexe a foto novamente na evolução.');
        }

        $authorizeRecovery = $request->boolean('authorize_structure_recovery');

        /** @var User $uploader */
        $uploader = Auth::user();

        try {
            $result = $driveService->uploadPhoto(
                $patient,
                $uploader,
                $localFullPath,
                $photo->filename,
                $photo->mime_type,
                [
                    'categoria'      => $photo->categoria,
                    'taken_at'       => $photo->taken_at,
                    'uploaded_by_id' => $uploader->id,
                ],
                $authorizeRecovery
            );

            $result['photo']->update(['clinical_evolution_id' => $photo->clinical_evolution_id]);

            Storage::disk('local')->delete($photo->local_path);
            $photo->delete();

            return back()->with('success', 'Foto enviada com sucesso.');
        } catch (GoogleDriveReauthRequiredException) {
            return Inertia::location($driveService->getAuthUrl());
        } catch (DriveStructureMissingException) {
            $photo->update(['failure_reason' => 'drive_structure_missing']);

            return back()->with('disaster_recovery_required', true);
        } catch (\Throwable $e) {
            Log::error('[PatientEvolutionController@retryPhoto] Falha ao reenviar foto pendente', [
                'patient_id' => $patient->id,
                'photo_id'   => $photo->id,
                'error'      => $e->getMessage(),
            ]);

            return back()->with('error', 'Não foi possível enviar a foto. Tente novamente em instantes.');
        }
    }
}
