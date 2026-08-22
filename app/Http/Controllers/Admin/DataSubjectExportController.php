<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\DataSubjectExport;
use App\Models\Patient;
use App\Models\User;
use App\Services\Admin\DataSubjectExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Atendimento de solicitações LGPD recebidas por chat/suporte — nunca
 * autoatendimento do titular (não há rota pública, nem e-mail automático,
 * nem status voltado ao titular). Autorização: só o middleware
 * `system-admin` do grupo de rotas, mesmo padrão de todo o restante de
 * Admin\* (nenhum controller admin usa Policy/Gate adicional).
 */
class DataSubjectExportController extends Controller
{
    public function searchUsers(Request $request, DataSubjectExportService $service): \Illuminate\Http\JsonResponse
    {
        $term = trim((string) $request->query('q'));

        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        return response()->json(['results' => $service->searchUsers($term)]);
    }

    public function exportUser(User $user, DataSubjectExportService $service): \Illuminate\Http\JsonResponse
    {
        $data = $service->exportUser($user);

        AccessLog::record(
            action: 'admin_user_data_exported',
            description: 'Exportação de dados do titular (usuário) para atendimento de solicitação LGPD',
            metadata: ['subject_type' => 'user', 'subject_id' => $user->id],
        );

        return response()->json($data)
            ->header('Content-Disposition', 'attachment; filename="titular-usuario-' . $user->id . '.json"');
    }

    public function searchPatients(Request $request, DataSubjectExportService $service): \Illuminate\Http\JsonResponse
    {
        $term = trim((string) $request->query('q'));

        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        return response()->json(['results' => $service->searchPatients($term)]);
    }

    public function exportPatient(int $patient, DataSubjectExportService $service): \Illuminate\Http\JsonResponse
    {
        $patientModel = $service->findPatientForExport($patient);

        $export = $service->startPatientExport($patientModel, Auth::user());

        AccessLog::record(
            action: 'admin_patient_data_exported',
            description: 'Exportação completa de dados do paciente para atendimento de solicitação LGPD',
            metadata: ['subject_type' => 'patient', 'subject_id' => $patientModel->id],
            clinicId: $patientModel->clinic_id,
        );

        return response()->json(['export_id' => $export->id, 'status' => $export->generation_status], 202);
    }

    public function status(DataSubjectExport $export): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'id'     => $export->id,
            'status' => $export->generation_status,
            'reason' => $export->generation_failed_reason,
        ]);
    }

    public function download(DataSubjectExport $export): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless($export->generation_status === DataSubjectExport::GENERATION_READY && $export->storage_path, 404);

        $filename = 'titular-paciente-' . $export->subject_id . '.zip';

        // Sem exists() prévio de propósito: é uma chamada Flysystem (HEAD/
        // ListBucket) separada do GET que download() já faz — em ambientes
        // com credenciais restritas a Put/Get num prefixo, o HEAD pode falhar
        // mesmo com o objeto lá (visto localmente). O 404 sai do próprio
        // catch, sem checagem redundante.
        try {
            return Storage::disk('s3')->download($export->storage_path, $filename);
        } catch (\Throwable $e) {
            abort(404);
        }
    }
}
