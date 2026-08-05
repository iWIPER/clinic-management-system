<?php

namespace App\Http\Controllers;

use App\Enums\Anamnesis\InstanceStatus;
use App\Models\AnamnesisActivityLog;
use App\Models\AnamnesisInstance;
use App\Models\Patient;
use App\Services\Signature\LocalSignatureProvider;
use Illuminate\Http\Request;

class AnamnesisSignatureController extends Controller
{
    public function __construct(private LocalSignatureProvider $provider) {}

    public function store(Request $request, Patient $patient, AnamnesisInstance $anamnesis)
    {
        abort_unless($anamnesis->patient_id === $patient->id, 404);

        if ($anamnesis->isSigned()) {
            return response()->json(['error' => 'Paciente já assinou esta anamnese.'], 422);
        }

        $validated = $request->validate([
            'signature_data' => 'required|string',
            'patient_name'   => 'required|string|max:160',
            'patient_cpf'    => 'nullable|string|max:20',
            'patient_email'  => 'nullable|email|max:160',
            'timezone'       => 'nullable|string|max:64',
            'browser_info'   => 'nullable|array',
            'geolocation'    => 'nullable|array',
        ]);

        $validated['user_agent'] = $request->userAgent();

        $signature = $this->provider->sign($anamnesis, $validated, $request->ip());

        AnamnesisActivityLog::create([
            'clinic_id'   => $anamnesis->clinic_id,
            'instance_id' => $anamnesis->id,
            'patient_id'  => $anamnesis->patient_id,
            'template_id' => $anamnesis->template_id,
            'action'      => 'patient_signed',
            'user_id'     => auth()->id(),
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'metadata'    => [
                'patient_name'   => $validated['patient_name'],
                'method'         => $signature->method(),
                'signature_hash' => $signature->signature_hash,
            ],
        ]);

        $fresh = $anamnesis->fresh();

        return response()->json([
            'signature' => $this->serializePatientSignature($signature),
            'instance'  => $this->serializeInstanceStatus($fresh),
        ]);
    }

    public function storeDentist(Request $request, Patient $patient, AnamnesisInstance $anamnesis)
    {
        abort_unless($anamnesis->patient_id === $patient->id, 404);

        // Apenas o profissional responsável pela instância pode assinar
        if ((int) auth()->id() !== (int) $anamnesis->professional_id) {
            return response()->json([
                'error' => 'Apenas o profissional responsável pelo atendimento pode assinar este documento.',
            ], 403);
        }

        // Paciente precisa ter assinado primeiro
        if (! $anamnesis->isSigned()) {
            return response()->json([
                'error' => 'O paciente precisa assinar antes do profissional.',
            ], 422);
        }

        // Dentista não pode assinar duas vezes
        if ($anamnesis->isFullySigned()) {
            return response()->json(['error' => 'Este documento já foi completamente assinado.'], 422);
        }

        $validated = $request->validate([
            'signature_data' => 'required|string',
            'timezone'       => 'nullable|string|max:64',
            'browser_info'   => 'nullable|array',
        ]);

        $professional = auth()->user();

        $validated['user_agent']      = $request->userAgent();
        $validated['professional_id'] = $professional->id;
        $validated['professional_cro'] = $professional->cro
            ? ($professional->cro . ($professional->cro_uf ? '/' . $professional->cro_uf : ''))
            : null;
        $validated['patient_name'] = $professional->name; // usado como signer name

        $signature = $this->provider->signAsDentist($anamnesis, $validated, null);

        AnamnesisActivityLog::create([
            'clinic_id'   => $anamnesis->clinic_id,
            'instance_id' => $anamnesis->id,
            'patient_id'  => $anamnesis->patient_id,
            'template_id' => $anamnesis->template_id,
            'action'      => 'dentist_signed',
            'user_id'     => $professional->id,
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'metadata'    => [
                'professional_name' => $professional->name,
                'professional_cro'  => $validated['professional_cro'],
                'method'            => 'Presencial',
                'signature_hash'    => $signature->signature_hash,
            ],
        ]);

        $fresh = $anamnesis->fresh();

        return response()->json([
            'dentist_signature' => $this->serializeDentistSignature($signature),
            'instance'          => $this->serializeInstanceStatus($fresh),
        ]);
    }

    private function serializePatientSignature(\App\Models\AnamnesisSignature $sig): array
    {
        return [
            'id'             => $sig->id,
            'patient_name'   => $sig->patient_name,
            'patient_cpf'    => $sig->patient_cpf,
            'patient_email'  => $sig->patient_email,
            'method'         => $sig->method(),
            'signature_url'  => $sig->signatureUrl(),
            'signature_hash' => $sig->signature_hash,
            'timezone'       => $sig->timezone,
            'signed_at'      => $sig->signed_at->toIso8601String(),
        ];
    }

    private function serializeDentistSignature(\App\Models\AnamnesisSignature $sig): array
    {
        return [
            'id'               => $sig->id,
            'professional_name' => $sig->patient_name, // armazenado em patient_name
            'professional_cro' => $sig->professional_cro,
            'method'           => 'Presencial',
            'signature_url'    => $sig->signatureUrl(),
            'signature_hash'   => $sig->signature_hash,
            'timezone'         => $sig->timezone,
            'signed_at'        => $sig->signed_at->toIso8601String(),
        ];
    }

    private function serializeInstanceStatus(AnamnesisInstance $instance): array
    {
        return [
            'status'          => $instance->status,
            'status_label'    => $instance->statusEnum()->label(),
            'is_signed'       => $instance->isSigned(),
            'is_fully_signed' => $instance->isFullySigned(),
            'signed_at'       => $instance->signed_at?->toIso8601String(),
        ];
    }
}
