<?php

namespace App\Services\Signature;

use App\Contracts\Signature\SignatureAdapterInterface;
use App\Models\AnamnesisInstance;
use Illuminate\Support\Facades\Http;

/**
 * Adapter para ZapSign (https://zapsign.com.br).
 * Documentação: https://docs.zapsign.com.br
 *
 * Configure em .env:
 *   ZAPSIGN_API_TOKEN=your_token_here
 *   ZAPSIGN_BASE_URL=https://api.zapsign.com.br/api/v1
 */
class ZapSignAdapter implements SignatureAdapterInterface
{
    private string $token;
    private string $baseUrl;

    public function __construct()
    {
        $this->token = config('services.zapsign.token', '');
        $this->baseUrl = config('services.zapsign.base_url', 'https://api.zapsign.com.br/api/v1');
    }

    public function requestSignature(AnamnesisInstance $instance, array $signers): array
    {
        // $signers = [['name' => '', 'email' => '', 'phone' => '']]
        $instance->load(['patient', 'professional']);

        $response = Http::withToken($this->token)
            ->post("{$this->baseUrl}/docs/", [
                'name' => 'Anamnese — ' . $instance->patient?->nome_completo,
                'url_pdf' => url("/storage/{$instance->pdf_path}"),
                'signers' => collect($signers)->map(fn ($s) => [
                    'name' => $s['name'],
                    'email' => $s['email'],
                    'phone_country' => '55',
                    'phone_number' => $s['phone'] ?? '',
                    'auth_mode' => 'assinaturaTela',
                    'send_automatic_email' => true,
                    'send_automatic_whatsapp' => isset($s['phone']),
                ])->all(),
            ]);

        $response->throw();
        $data = $response->json();

        return [
            'provider_id' => $data['token'],
            'sign_url' => $data['signers'][0]['sign_url'] ?? null,
            'driver' => $this->driver(),
            'raw' => $data,
        ];
    }

    public function checkStatus(string $providerId): array
    {
        $response = Http::withToken($this->token)
            ->get("{$this->baseUrl}/docs/{$providerId}/");

        $response->throw();
        $data = $response->json();

        $status = match ($data['status'] ?? '') {
            'signed' => 'signed',
            'expired' => 'expired',
            'cancelled' => 'cancelled',
            default => 'pending',
        };

        return [
            'status' => $status,
            'signed_at' => $data['signed_at'] ?? null,
        ];
    }

    public function cancel(string $providerId): bool
    {
        $response = Http::withToken($this->token)
            ->delete("{$this->baseUrl}/docs/{$providerId}/");

        return $response->successful();
    }

    public function downloadSigned(string $providerId): string
    {
        $response = Http::withToken($this->token)
            ->get("{$this->baseUrl}/docs/{$providerId}/");

        $response->throw();
        $pdfUrl = $response->json('signed_pdf_url');

        return Http::get($pdfUrl)->throw()->body();
    }

    public function driver(): string
    {
        return 'zapsign';
    }
}
