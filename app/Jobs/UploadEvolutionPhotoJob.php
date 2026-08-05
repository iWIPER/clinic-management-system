<?php

namespace App\Jobs;

use App\Exceptions\DriveStructureMissingException;
use App\Exceptions\GoogleDriveReauthRequiredException;
use App\Models\PatientPhoto;
use App\Services\GoogleDriveService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Envia em segundo plano a foto de uma evolução clínica já salva localmente
 * (ver PatientEvolutionController::store()). A linha PatientPhoto já existe
 * com status 'uploading' antes deste job rodar — este job só atualiza essa
 * mesma linha (nunca cria uma nova), refletindo sucesso ou marcando como
 * pendente com o motivo certo, do mesmo jeito que o retry síncrono já faz.
 *
 * Sem retries automáticos ($tries=1): as causas de falha aqui (estrutura do
 * Drive ausente, reautenticação necessária) não se resolvem tentando de novo
 * sozinhas — precisam de uma ação do usuário (botão "Reenviar"/"Reconectar
 * Drive"/"Recriar estrutura" na tela), que é o mecanismo de retry real.
 */
class UploadEvolutionPhotoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public int $photoId,
        public string $folderId,
    ) {}

    public function handle(GoogleDriveService $driveService): void
    {
        $photo = PatientPhoto::find($this->photoId);

        if (! $photo || $photo->status !== 'uploading') {
            return;
        }

        if (! $photo->local_path || ! Storage::disk('local')->exists($photo->local_path)) {
            $photo->update(['status' => 'pending', 'failure_reason' => null]);

            Log::error('[UploadEvolutionPhotoJob] Arquivo local não encontrado', [
                'photo_id' => $photo->id,
            ]);

            return;
        }

        $localFullPath = Storage::disk('local')->path($photo->local_path);

        try {
            $result = $driveService->uploadToKnownFolder(
                $photo->patient,
                $this->folderId,
                $localFullPath,
                $photo->filename,
                $photo->mime_type
            );

            $photo->update([
                'status'          => 'active',
                'drive_file_id'   => $result['drive_file_id'],
                'drive_folder_id' => $this->folderId,
            ]);

            Storage::disk('local')->delete($photo->local_path);
            $photo->update(['local_path' => null]);
        } catch (GoogleDriveReauthRequiredException) {
            $photo->update(['status' => 'pending', 'failure_reason' => 'drive_reauth_required']);
        } catch (DriveStructureMissingException) {
            $photo->update(['status' => 'pending', 'failure_reason' => 'drive_structure_missing']);
        } catch (\Throwable $e) {
            $photo->update(['status' => 'pending', 'failure_reason' => null]);

            Log::error('[UploadEvolutionPhotoJob] Falha ao enviar foto da evolução', [
                'photo_id' => $photo->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Rede de segurança: se algo escapar do try/catch acima (ex: erro de
     * banco) e o job for descartado pelo worker, a foto nunca deve ficar
     * presa em "uploading" pra sempre.
     */
    public function failed(\Throwable $exception): void
    {
        $photo = PatientPhoto::find($this->photoId);

        if ($photo && $photo->status === 'uploading') {
            $photo->update(['status' => 'pending', 'failure_reason' => null]);
        }

        Log::error('[UploadEvolutionPhotoJob] Falha não tratada, job descartado', [
            'photo_id' => $this->photoId,
            'error'    => $exception->getMessage(),
        ]);
    }
}
