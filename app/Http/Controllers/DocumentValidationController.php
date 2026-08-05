<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentSignature;
use Illuminate\View\View;

class DocumentValidationController extends Controller
{
    public function show(string $token): View
    {
        $document = Document::query()
            ->where('validation_token', $token)
            ->with(['patient', 'professional', 'clinic', 'signatures', 'template'])
            ->first();

        if (! $document) {
            return view('document-validate', ['valid' => false]);
        }

        $signers = collect($document->requiredSignerRoles())->map(function (string $role) use ($document) {
            $sig = $document->signatures->firstWhere('signer_role', $role);

            return [
                'role_label'  => (new DocumentSignature(['signer_role' => $role]))->roleLabel(),
                'signed'      => (bool) $sig,
                'signer_name' => $sig?->signer_name,
                'signed_at'   => $sig?->signed_at?->format('d/m/Y H:i'),
            ];
        })->values()->all();

        return view('document-validate', [
            'valid'          => true,
            'document_title' => $document->template_name,
            'patient_name'   => $document->patient?->nome_completo ?? '—',
            'clinic_name'    => $document->clinic?->displayName() ?? '—',
            'document_code'  => $document->document_code,
            'status_label'   => $document->statusEnum()->label(),
            'created_at'     => $document->created_at->format('d/m/Y H:i'),
            'document_hash'  => $document->content_hash ?? hash('sha256', $token . $document->id . $document->created_at->toIso8601String()),
            'signers'        => $signers,
        ]);
    }
}
