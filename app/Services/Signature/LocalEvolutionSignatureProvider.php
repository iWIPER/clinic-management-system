<?php

namespace App\Services\Signature;

use App\Models\ClinicalEvolution;
use App\Models\ClinicalEvolutionSignature;
use Illuminate\Support\Facades\Storage;

/**
 * Provedor local de assinatura eletrônica via canvas (Signature Pad) para
 * Evoluções Clínicas. Assinatura única (paciente/responsável) — o
 * profissional autor da evolução já fica registrado em professional_id na
 * criação, não precisa de uma segunda assinatura como na Anamnese.
 * Espelha LocalSignatureProvider (Anamnese), sem o lado "dentista".
 */
class LocalEvolutionSignatureProvider
{
    public function sign(ClinicalEvolution $evolution, array $data, ?string $ip): ClinicalEvolutionSignature
    {
        $pngBinary = $this->decodeBase64Png($data['signature_data']);
        $hash      = hash('sha256', $pngBinary);
        $path      = "signatures/evolution-{$evolution->id}-{$hash}.png";

        Storage::disk('public')->put($path, $pngBinary);

        return ClinicalEvolutionSignature::create([
            'clinic_id'      => $evolution->clinic_id,
            'evolution_id'   => $evolution->id,
            'patient_name'   => $data['patient_name'] ?? null,
            'patient_cpf'    => $data['patient_cpf'] ?? null,
            'patient_email'  => $data['patient_email'] ?? null,
            'signature_path' => $path,
            'signature_hash' => $hash,
            'ip_address'     => $ip,
            'user_agent'     => $data['user_agent'] ?? null,
            'timezone'       => $data['timezone'] ?? null,
            'browser_info'   => $data['browser_info'] ?? null,
            'geolocation'    => $data['geolocation'] ?? null,
            'signed_at'      => now(),
        ]);
    }

    private function decodeBase64Png(string $dataUri): string
    {
        $base64 = $dataUri;
        if (str_contains($dataUri, ',')) {
            $base64 = explode(',', $dataUri, 2)[1];
        }

        return base64_decode($base64, true) ?: throw new \RuntimeException('Invalid signature PNG data.');
    }
}
