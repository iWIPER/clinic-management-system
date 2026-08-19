<?php

namespace App\Http\Controllers;

use App\Mail\DocumentSignatureRequestMail;
use App\Models\Document;
use App\Models\DocumentActivityLog;
use App\Models\DocumentSignature;
use App\Models\Patient;
use App\Services\Documents\DocumentStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class DocumentSignaturePanelController extends Controller
{
    public function show(Patient $patient, Document $document)
    {
        abort_unless($document->patient_id === $patient->id, 404);
        $document->load(['signatures', 'template']);

        return response()->json($this->serialize($document));
    }

    public function generateLink(Request $request, Patient $patient, Document $document)
    {
        abort_unless($document->patient_id === $patient->id, 404);

        $hours = $document->template?->signature_expiration_hours ?? 72;

        $document->update([
            'signature_token'            => bin2hex(random_bytes(32)),
            'signature_token_expires_at' => now()->addHours($hours),
        ]);

        DocumentActivityLog::create([
            'clinic_id'   => $document->clinic_id,
            'document_id' => $document->id,
            'patient_id'  => $document->patient_id,
            'document_template_id' => $document->template_id,
            'action'      => 'link_generated',
            'user_id'     => $request->user()?->id,
            'ip_address'  => $request->ip(),
        ]);

        return response()->json([
            'sign_url'   => route('documents.public-sign', $document->signature_token),
            'expires_at' => $document->signature_token_expires_at->toIso8601String(),
        ]);
    }

    public function sendEmail(Request $request, Patient $patient, Document $document)
    {
        abort_unless($document->patient_id === $patient->id, 404);

        if (! $patient->email) {
            return response()->json(['error' => 'Paciente não possui e-mail cadastrado.'], 422);
        }

        if (! $document->signature_token || $document->signature_token_expires_at?->isPast()) {
            $this->generateLink($request, $patient, $document);
            $document->refresh();
        }

        // Fase B5: sem anexo, sem segredo no payload (só um link com token já
        // persistido) — enfileirado pra não bloquear a resposta no SMTP.
        Mail::to($patient->email)->queue(
            new DocumentSignatureRequestMail($document, route('documents.public-sign', $document->signature_token))
        );

        DocumentActivityLog::create([
            'clinic_id'   => $document->clinic_id,
            'document_id' => $document->id,
            'patient_id'  => $document->patient_id,
            'document_template_id' => $document->template_id,
            'action'      => 'sent_email',
            'user_id'     => $request->user()?->id,
            'metadata'    => ['email' => $patient->email],
        ]);

        return response()->json(['sent' => true]);
    }

    public function logWhatsapp(Request $request, Patient $patient, Document $document)
    {
        abort_unless($document->patient_id === $patient->id, 404);

        DocumentActivityLog::create([
            'clinic_id'   => $document->clinic_id,
            'document_id' => $document->id,
            'patient_id'  => $document->patient_id,
            'document_template_id' => $document->template_id,
            'action'      => 'sent_whatsapp',
            'user_id'     => $request->user()?->id,
        ]);

        return response()->json(['logged' => true]);
    }

    public function cancel(Request $request, Patient $patient, Document $document, DocumentStatusService $statusService)
    {
        abort_unless($document->patient_id === $patient->id, 404);

        $reason = $request->input('reason', 'Cancelado pelo usuário.');
        $statusService->cancel($document, $reason, $request->user()?->id);

        return response()->json($this->serialize($document->fresh()->load(['signatures', 'template'])));
    }

    private function serialize(Document $document): array
    {
        $required = $document->requiredSignerRoles();
        $signatures = $document->signatures->keyBy('signer_role');

        $signers = collect($required)->map(function (string $role) use ($signatures) {
            /** @var ?DocumentSignature $sig */
            $sig = $signatures->get($role);

            return [
                'role'       => $role,
                'role_label' => (new DocumentSignature(['signer_role' => $role]))->roleLabel(),
                'status'     => $sig ? 'signed' : 'pending',
                'signer_name' => $sig?->signer_name,
                'signed_at'  => $sig?->signed_at?->format('d/m/Y H:i'),
                'method'     => $sig?->method(),
                'signature_url' => $sig?->signatureUrl(),
            ];
        })->values()->all();

        return [
            'status'          => $document->status,
            'status_label'    => $document->statusEnum()->label(),
            'status_color'    => $document->statusEnum()->color(),
            'is_fully_signed' => $document->isFullySigned(),
            'signers'         => $signers,
            'sign_url'        => $document->signature_token && ! $document->signature_token_expires_at?->isPast()
                ? route('documents.public-sign', $document->signature_token)
                : null,
            'signature_token_expires_at' => $document->signature_token_expires_at?->toIso8601String(),
        ];
    }
}
