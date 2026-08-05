<?php

namespace App\Http\Controllers;

use App\Models\AnamnesisInstance;
use Illuminate\View\View;

class AnamnesisValidationController extends Controller
{
    public function show(string $token): View
    {
        $instance = AnamnesisInstance::query()
            ->where('validation_token', $token)
            ->with(['patient', 'professional', 'clinic', 'patientSignature', 'dentistSignature'])
            ->first();

        if (! $instance) {
            return view('anamnesis-validate', ['valid' => false]);
        }

        $sig      = $instance->patientSignature;
        $dentSig  = $instance->dentistSignature;

        $signatureInfo = [
            'signed'              => $instance->isSigned(),
            'fully_signed'        => $instance->isFullySigned(),
            'patient_name'        => $sig?->patient_name ?? '—',
            'signed_at'           => $sig?->signed_at?->format('d/m/Y H:i'),
            'method'              => $sig?->method() ?? '—',
            'dentist_name'        => $dentSig?->patient_name ?? null,
            'dentist_cro'         => $dentSig?->professional_cro ?? null,
            'dentist_signed_at'   => $dentSig?->signed_at?->format('d/m/Y H:i'),
        ];

        return view('anamnesis-validate', [
            'valid' => true,
            'patient_name' => $instance->patient?->nome_completo ?? '—',
            'professional_name' => $instance->professional?->name ?? '—',
            'clinic_name' => $instance->clinic?->displayName() ?? '—',
            'document_date' => $instance->effectiveDate()->format('d/m/Y H:i'),
            'status' => $instance->status,
            'status_label' => $instance->statusEnum()->label(),
            'created_at' => $instance->created_at->format('d/m/Y H:i'),
            'document_hash' => hash('sha256', $token . $instance->id . $instance->created_at->toIso8601String()),
            'signature_info' => $signatureInfo,
        ]);
    }
}
