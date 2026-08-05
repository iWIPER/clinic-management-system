<?php

namespace App\Services\Signature;

use App\Contracts\Signature\SignatureAdapterInterface;
use App\Models\AnamnesisInstance;
use Illuminate\Support\Facades\Http;

/**
 * Adapter para Autentique (https://autentique.com.br).
 * Documentação: https://docs.autentique.com.br/api/v2
 * API GraphQL — endpoint: https://api.autentique.com.br/2/graphql
 *
 * Configure em .env:
 *   AUTENTIQUE_API_TOKEN=your_token_here
 */
class AutentiqueAdapter implements SignatureAdapterInterface
{
    private string $token;
    private string $endpoint = 'https://api.autentique.com.br/2/graphql';

    public function __construct()
    {
        $this->token = config('services.autentique.token', '');
    }

    public function requestSignature(AnamnesisInstance $instance, array $signers): array
    {
        $instance->load(['patient']);

        $signersGql = collect($signers)->map(fn ($s) => [
            'email' => $s['email'],
            'name' => $s['name'],
            'action' => 'SIGN',
        ])->all();

        $mutation = <<<'GQL'
        mutation CreateDocument($document: DocumentInput!, $signers: [SignerInput!]!, $file: Upload!) {
            createDocument(
                document: $document,
                signers: $signers,
                file: $file
            ) {
                id
                name
                signers {
                    email
                    link { short_link }
                }
            }
        }
        GQL;

        $response = Http::withToken($this->token)
            ->attach('file', file_get_contents(storage_path("app/public/{$instance->pdf_path}")), 'anamnese.pdf')
            ->post($this->endpoint, [
                'query' => $mutation,
                'variables' => json_encode([
                    'document' => ['name' => 'Anamnese — ' . $instance->patient?->nome_completo],
                    'signers' => $signersGql,
                ]),
            ]);

        $response->throw();
        $data = $response->json('data.createDocument');

        return [
            'provider_id' => $data['id'],
            'sign_url' => $data['signers'][0]['link']['short_link'] ?? null,
            'driver' => $this->driver(),
            'raw' => $data,
        ];
    }

    public function checkStatus(string $providerId): array
    {
        $query = <<<'GQL'
        query Document($id: UUID!) {
            document(id: $id) {
                id
                signatures { signed signed_at { date } }
            }
        }
        GQL;

        $response = Http::withToken($this->token)
            ->post($this->endpoint, [
                'query' => $query,
                'variables' => ['id' => $providerId],
            ]);

        $response->throw();
        $doc = $response->json('data.document');

        $allSigned = collect($doc['signatures'] ?? [])->every(fn ($s) => $s['signed']);
        $signedAt = collect($doc['signatures'] ?? [])->map(fn ($s) => $s['signed_at']['date'] ?? null)->filter()->last();

        return [
            'status' => $allSigned ? 'signed' : 'pending',
            'signed_at' => $signedAt,
        ];
    }

    public function cancel(string $providerId): bool
    {
        $mutation = <<<'GQL'
        mutation DeleteDocument($id: UUID!) {
            deleteDocument(id: $id)
        }
        GQL;

        $response = Http::withToken($this->token)
            ->post($this->endpoint, [
                'query' => $mutation,
                'variables' => ['id' => $providerId],
            ]);

        return $response->successful();
    }

    public function downloadSigned(string $providerId): string
    {
        $query = <<<'GQL'
        query Document($id: UUID!) {
            document(id: $id) {
                files { signed }
            }
        }
        GQL;

        $response = Http::withToken($this->token)
            ->post($this->endpoint, [
                'query' => $query,
                'variables' => ['id' => $providerId],
            ]);

        $response->throw();
        $url = $response->json('data.document.files.signed');

        return Http::get($url)->throw()->body();
    }

    public function driver(): string
    {
        return 'autentique';
    }
}
