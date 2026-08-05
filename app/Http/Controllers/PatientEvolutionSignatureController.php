<?php

namespace App\Http\Controllers;

use App\Models\ClinicalEvolution;
use App\Models\Patient;
use App\Services\Signature\LocalEvolutionSignatureProvider;
use Illuminate\Http\Request;

class PatientEvolutionSignatureController extends Controller
{
    public function __construct(private LocalEvolutionSignatureProvider $provider) {}

    public function store(Request $request, Patient $patient, ClinicalEvolution $evolution)
    {
        abort_unless($evolution->patient_id === $patient->id, 404);

        if (! $evolution->signature_required) {
            return response()->json(['error' => 'Esta evolução não exige assinatura.'], 422);
        }

        if ($evolution->signature()->exists()) {
            return response()->json(['error' => 'Esta evolução já foi assinada.'], 422);
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

        $signature = $this->provider->sign($evolution, $validated, $request->ip());

        return response()->json([
            'signature' => [
                'id'             => $signature->id,
                'patient_name'   => $signature->patient_name,
                'patient_cpf'    => $signature->patient_cpf,
                'patient_email'  => $signature->patient_email,
                'signature_url'  => $signature->signatureUrl(),
                'signature_hash' => $signature->signature_hash,
                'signed_at'      => $signature->signed_at->toIso8601String(),
            ],
        ]);
    }
}
