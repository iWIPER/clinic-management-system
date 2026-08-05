<?php

namespace App\Services\Signature;

use App\Contracts\Signature\SignatureProviderInterface;
use App\Enums\Anamnesis\InstanceStatus;
use App\Models\AnamnesisSignature;
use App\Models\AnamnesisInstance;
use Illuminate\Support\Facades\Storage;

/**
 * Provedor local de assinatura eletrônica via canvas (Signature Pad).
 * Armazena o PNG diretamente no storage sem dependência de terceiros.
 * Funciona offline, em tablet, mouse, touch e caneta digital.
 */
class LocalSignatureProvider implements SignatureProviderInterface
{
    public function sign(AnamnesisInstance $instance, array $data, ?string $ip): AnamnesisSignature
    {
        return $this->signAs($instance, $data, 'patient', $ip);
    }

    public function signAsDentist(AnamnesisInstance $instance, array $data, ?string $ip): AnamnesisSignature
    {
        return $this->signAs($instance, $data, 'dentist', $ip);
    }

    private function signAs(AnamnesisInstance $instance, array $data, string $signerType, ?string $ip): AnamnesisSignature
    {
        $pngBinary = $this->decodeBase64Png($data['signature_data']);
        $hash      = hash('sha256', $pngBinary);
        $path      = "signatures/instance-{$instance->id}-{$signerType}-{$hash}.png";

        Storage::disk('public')->put($path, $pngBinary);

        $signature = AnamnesisSignature::create([
            'clinic_id'        => $instance->clinic_id,
            'instance_id'      => $instance->id,
            'signer_type'      => $signerType,
            'professional_id'  => $data['professional_id'] ?? null,
            'professional_cro' => $data['professional_cro'] ?? null,
            'patient_name'     => $data['patient_name'] ?? null,
            'patient_cpf'      => $data['patient_cpf'] ?? null,
            'patient_email'    => $data['patient_email'] ?? null,
            'signature_path'   => $path,
            'signature_hash'   => $hash,
            'ip_address'       => $ip,
            'user_agent'       => $data['user_agent'] ?? null,
            'timezone'         => $data['timezone'] ?? null,
            'browser_info'     => $data['browser_info'] ?? null,
            'geolocation'      => $data['geolocation'] ?? null,
            'signed_at'        => now(),
        ]);

        if ($signerType === 'patient') {
            $instance->update([
                'status'    => InstanceStatus::Signed->value,
                'signed_at' => now(),
            ]);
        } else {
            $instance->update([
                'status' => InstanceStatus::FullySigned->value,
            ]);
        }

        return $signature;
    }

    public function driver(): string
    {
        return 'local';
    }

    public function supportsOAuth(): bool
    {
        return false;
    }

    private function decodeBase64Png(string $dataUri): string
    {
        // Accepts 'data:image/png;base64,XXXX' or raw base64
        $base64 = $dataUri;
        if (str_contains($dataUri, ',')) {
            $base64 = explode(',', $dataUri, 2)[1];
        }

        return base64_decode($base64, true) ?: throw new \RuntimeException('Invalid signature PNG data.');
    }
}
