<?php

namespace App\Http\Controllers;

use App\Enums\Documents\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentActivityLog;
use App\Models\DocumentSignature;
use App\Models\Patient;
use App\Services\Documents\LocalDocumentSignatureProvider;
use Illuminate\Http\Request;

class DocumentSignatureController extends Controller
{
    private const ROLES = ['patient', 'professional', 'responsible', 'witness'];

    public function __construct(private LocalDocumentSignatureProvider $provider) {}

    public function store(Request $request, Patient $patient, Document $document, string $role)
    {
        abort_unless($document->patient_id === $patient->id, 404);
        abort_unless(in_array($role, self::ROLES, true), 404);

        if (in_array($document->status, [DocumentStatus::Cancelled->value, DocumentStatus::Expired->value], true)) {
            return response()->json(['error' => 'Este documento não pode mais ser assinado.'], 422);
        }

        if (! in_array($role, $document->requiredSignerRoles(), true)) {
            return response()->json(['error' => 'Este documento não exige assinatura deste papel.'], 422);
        }

        if ($role !== DocumentSignature::ROLE_WITNESS
            && $document->signatures()->where('signer_role', $role)->exists()) {
            return response()->json(['error' => 'Este papel já assinou este documento.'], 422);
        }

        if ($role === DocumentSignature::ROLE_PROFESSIONAL) {
            abort_unless((int) $request->user()->id === (int) $document->professional_id, 403);

            $validated = $request->validate([
                'signature_data' => 'required|string',
                'timezone'       => 'nullable|string|max:64',
                'browser_info'   => 'nullable|array',
            ]);

            $professional = $request->user();

            $data = $validated + [
                'professional_id'  => $professional->id,
                'professional_cro' => $professional->cro
                    ? ($professional->cro . ($professional->cro_uf ? '/' . $professional->cro_uf : ''))
                    : null,
                'signer_name' => $professional->name,
            ];
        } else {
            $data = $request->validate([
                'signature_data' => 'required|string',
                'signer_name'    => 'required|string|max:160',
                'signer_cpf'     => 'nullable|string|max:20',
                'signer_email'   => 'nullable|email|max:160',
                'timezone'       => 'nullable|string|max:64',
                'browser_info'   => 'nullable|array',
                'geolocation'    => 'nullable|array',
            ]);
        }

        $data['user_agent'] = $request->userAgent();

        $signature = $this->provider->sign($document, $role, $data, $request->ip());

        DocumentActivityLog::create([
            'clinic_id'   => $document->clinic_id,
            'document_id' => $document->id,
            'patient_id'  => $document->patient_id,
            'document_template_id' => $document->template_id,
            'action'      => $role . '_signed',
            'user_id'     => $request->user()?->id,
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'metadata'    => [
                'signer_name'    => $signature->signer_name,
                'signature_hash' => $signature->signature_hash,
            ],
        ]);

        $fresh = $document->fresh();

        return response()->json([
            'signature' => $this->serialize($signature),
            'document'  => [
                'status'           => $fresh->status,
                'status_label'     => $fresh->statusEnum()->label(),
                'is_fully_signed'  => $fresh->isFullySigned(),
                'pending_roles'    => $fresh->pendingSignerRoles(),
            ],
        ]);
    }

    private function serialize(DocumentSignature $sig): array
    {
        return [
            'id'             => $sig->id,
            'signer_role'    => $sig->signer_role,
            'role_label'     => $sig->roleLabel(),
            'signer_name'    => $sig->signer_name,
            'signature_url'  => $sig->signatureUrl(),
            'signature_hash' => $sig->signature_hash,
            'signed_at'      => $sig->signed_at->toIso8601String(),
        ];
    }
}
