<?php

namespace App\Http\Controllers\Public;

use App\Enums\Documents\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentActivityLog;
use App\Models\DocumentSignature;
use App\Services\Documents\LocalDocumentSignatureProvider;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Fluxo público (sem autenticação) de assinatura remota por token — capacidade
 * nova que a Anamnese não possui hoje (lá a assinatura é sempre presencial).
 * Por segurança, apenas os papéis patient/responsible/witness podem assinar
 * remotamente; a assinatura do profissional exige sessão autenticada (ver
 * DocumentSignatureController), pois é a única forma de validar sua identidade.
 */
class DocumentPublicSignatureController extends Controller
{
    private const PUBLIC_ROLES = ['patient', 'responsible', 'witness'];

    public function __construct(private LocalDocumentSignatureProvider $provider) {}

    public function show(string $token): View
    {
        $document = $this->findValidDocument($token);

        if (! $document) {
            return view('document-public-sign', ['valid' => false]);
        }

        $document->load(['patient', 'clinic', 'template', 'signatures']);

        $pendingRoles = array_values(array_intersect($document->pendingSignerRoles(), self::PUBLIC_ROLES));

        return view('document-public-sign', [
            'valid'         => true,
            'token'         => $token,
            'document'      => $document,
            'pendingRoles'  => $pendingRoles,
            'contentHtml'   => $document->rendered_html,
        ]);
    }

    public function store(Request $request, string $token)
    {
        $document = $this->findValidDocument($token);

        if (! $document) {
            return response()->json(['error' => 'Link inválido ou expirado.'], 410);
        }

        $role = $request->input('signer_role');

        if (! in_array($role, self::PUBLIC_ROLES, true) || ! in_array($role, $document->pendingSignerRoles(), true)) {
            return response()->json(['error' => 'Este documento não aceita esse tipo de assinatura por este link.'], 422);
        }

        $data = $request->validate([
            'signature_data' => 'required|string',
            'signer_name'    => 'required|string|max:160',
            'signer_cpf'     => 'nullable|string|max:20',
            'signer_email'   => 'nullable|email|max:160',
            'timezone'       => 'nullable|string|max:64',
            'browser_info'   => 'nullable|array',
            'geolocation'    => 'nullable|array',
        ]);
        $data['user_agent'] = $request->userAgent();

        $signature = $this->provider->sign($document, $role, $data, $request->ip());

        DocumentActivityLog::create([
            'clinic_id'   => $document->clinic_id,
            'document_id' => $document->id,
            'patient_id'  => $document->patient_id,
            'document_template_id' => $document->template_id,
            'action'      => $role . '_signed',
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'metadata'    => [
                'signer_name'    => $signature->signer_name,
                'signature_hash' => $signature->signature_hash,
                'via'            => 'public_link',
            ],
        ]);

        $fresh = $document->fresh();
        $stillPending = array_values(array_intersect($fresh->pendingSignerRoles(), self::PUBLIC_ROLES));

        if (empty($stillPending)) {
            $fresh->update(['signature_token' => null, 'signature_token_expires_at' => null]);
        }

        return response()->json([
            'status'       => $fresh->status,
            'status_label' => $fresh->statusEnum()->label(),
            'completed'    => $fresh->isFullySigned(),
        ]);
    }

    private function findValidDocument(string $token): ?Document
    {
        $document = Document::query()->where('signature_token', $token)->first();

        if (! $document) {
            return null;
        }

        if ($document->signature_token_expires_at && $document->signature_token_expires_at->isPast()) {
            app(\App\Services\Documents\DocumentStatusService::class)->markExpired($document);

            return null;
        }

        if (in_array($document->status, [DocumentStatus::Cancelled->value, DocumentStatus::Expired->value], true)) {
            return null;
        }

        return $document;
    }
}
