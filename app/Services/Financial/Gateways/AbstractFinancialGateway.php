<?php

namespace App\Services\Financial\Gateways;

use App\Contracts\Financial\FinancialGatewayInterface;
use App\DTO\Financial\FinancingProposalRequest;
use App\DTO\Financial\FinancingProposalResult;
use App\DTO\Financial\FinancingSimulationRequest;
use App\DTO\Financial\FinancingSimulationResult;
use App\DTO\Financial\IntegrationTestReport;
use App\Enums\Financial\FinancingProposalStatus;
use App\Enums\Financial\FinancingWebhookEventType;
use App\Exceptions\Financial\FinancialGatewayException;
use App\Exceptions\Financial\FinancialGatewayUnavailableException;
use App\Models\ClinicFinancialConnection;
use App\Services\Financial\CircuitBreaker;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

abstract class AbstractFinancialGateway implements FinancialGatewayInterface
{
    public function __construct(
        protected CircuitBreaker $circuitBreaker,
    ) {}

    abstract protected function providerSlug(): string;

    public function provider(): string
    {
        return $this->providerSlug();
    }

    public function displayName(): string
    {
        return config("financial.institutions.{$this->providerSlug()}.name", $this->providerSlug());
    }

    protected function institutionConfig(): array
    {
        return config("financial.institutions.{$this->providerSlug()}", []);
    }

    protected function endpoints(ClinicFinancialConnection $connection): array
    {
        $env = $connection->environment === 'production' ? 'production' : 'sandbox';

        return $this->institutionConfig()[$env] ?? [];
    }

    public function testIntegration(ClinicFinancialConnection $connection): IntegrationTestReport
    {
        $checks          = [];
        $recommendations = [];
        $started         = microtime(true);

        try {
            $this->circuitBreaker->guard($this->provider(), $connection->clinic_id);

            $hasCredentials = !empty($connection->client_id) && !empty($connection->client_secret);

            if (!$hasCredentials && !($connection->isSandbox() && $this->shouldUseMock($connection))) {
                $checks[] = ['key' => 'credentials', 'label' => 'Credenciais configuradas', 'status' => 'fail'];
                $recommendations[] = 'Informe Client ID e Client Secret da instituição financeira.';

                return $this->buildTestReport($connection, false, $checks, $recommendations, null);
            }

            $checks[] = [
                'key'     => 'credentials',
                'label'   => 'Credenciais configuradas',
                'status'  => 'ok',
                'message' => $hasCredentials ? null : 'Sandbox em modo simulado.',
            ];

            $token = $this->authenticate($connection, recordSuccess: false);
            $checks[] = ['key' => 'token', 'label' => 'Token válido', 'status' => 'ok'];

            $apiBase = $this->endpoints($connection)['api_base'] ?? null;
            if ($connection->isSandbox() || $this->shouldUseMock($connection)) {
                $checks[] = ['key' => 'communication', 'label' => 'Comunicação estabelecida', 'status' => 'ok', 'message' => 'Modo sandbox — resposta simulada.'];
                $checks[] = ['key' => 'permissions', 'label' => 'Permissões', 'status' => 'ok'];
                $checks[] = ['key' => 'endpoint', 'label' => 'Endpoint acessível', 'status' => 'ok'];
            } else {
                $response = Http::timeout(config('financial.http_timeout', 15))
                    ->withToken($token)
                    ->get(rtrim((string) $apiBase, '/') . '/health');

                $endpointOk = $response->successful() || $response->status() === 404;
                $checks[] = [
                    'key'    => 'endpoint',
                    'label'  => 'Endpoint acessível',
                    'status' => $endpointOk ? 'ok' : 'fail',
                ];
                $checks[] = ['key' => 'communication', 'label' => 'Comunicação estabelecida', 'status' => $response->successful() ? 'ok' : 'warning'];
                $checks[] = ['key' => 'permissions', 'label' => 'Permissões', 'status' => in_array($response->status(), [200, 404]) ? 'ok' : 'fail'];
            }

            $this->circuitBreaker->recordSuccess($this->provider(), $connection->clinic_id);
            $connection->update(['last_tested_at' => now(), 'status' => 'active']);

            $elapsed = (int) round((microtime(true) - $started) * 1000);
            $checks[] = [
                'key'     => 'latency',
                'label'   => 'Tempo de resposta',
                'status'  => $elapsed < 3000 ? 'ok' : 'warning',
                'message' => "{$elapsed}ms",
            ];
            $checks[] = [
                'key'     => 'environment',
                'label'   => 'Ambiente',
                'status'  => 'ok',
                'message' => $connection->environment,
            ];

            if (empty($recommendations)) {
                $recommendations[] = 'Integração funcionando corretamente.';
            }

            return $this->buildTestReport($connection, true, $checks, $recommendations, $elapsed);
        } catch (FinancialGatewayUnavailableException $e) {
            $checks[] = ['key' => 'circuit', 'label' => 'Disponibilidade', 'status' => 'fail', 'message' => 'Instituição temporariamente indisponível.'];
            $recommendations[] = 'Aguarde alguns minutos e tente novamente.';

            return $this->buildTestReport($connection, false, $checks, $recommendations, null);
        } catch (\Throwable $e) {
            $this->circuitBreaker->recordFailure($this->provider(), $connection->clinic_id);
            Log::warning("[FinancialGateway:{$this->provider()}] Falha no teste de integração", ['error' => $e->getMessage()]);

            $checks[] = ['key' => 'token', 'label' => 'Token válido', 'status' => 'fail', 'message' => $e->getMessage()];
            $recommendations[] = 'Verifique Client ID, Client Secret e ambiente configurado.';

            $connection->update(['status' => 'error', 'last_tested_at' => now()]);

            return $this->buildTestReport($connection, false, $checks, $recommendations, null);
        }
    }

    public function simulate(ClinicFinancialConnection $connection, FinancingSimulationRequest $request): array
    {
        $this->circuitBreaker->guard($this->provider(), $connection->clinic_id);

        try {
            if ($connection->isSandbox() || $this->shouldUseMock($connection)) {
                return $this->mockSimulations($request);
            }

            $token    = $this->authenticate($connection);
            $paths    = $this->endpoints($connection);
            $response = Http::timeout(config('financial.http_timeout', 15))
                ->withToken($token)
                ->post(rtrim($paths['api_base'], '/') . ($paths['simulate_path'] ?? '/simulacoes'), [
                    'cpf'      => $request->cpf,
                    'valor'    => $request->amount,
                    'parcelas' => $request->installments,
                ]);

            if (!$response->successful()) {
                throw new FinancialGatewayException(
                    "Simulação falhou: HTTP {$response->status()}",
                    $this->provider(),
                    'Não foi possível obter simulações desta instituição. Tente outra opção ou tente novamente.'
                );
            }

            $this->circuitBreaker->recordSuccess($this->provider(), $connection->clinic_id);

            return $this->mapSimulationResponse($response->json() ?? []);
        } catch (FinancialGatewayUnavailableException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->circuitBreaker->recordFailure($this->provider(), $connection->clinic_id);
            throw new FinancialGatewayException($e->getMessage(), $this->provider(), previous: $e);
        }
    }

    public function submitProposal(ClinicFinancialConnection $connection, FinancingProposalRequest $request): FinancingProposalResult
    {
        $this->circuitBreaker->guard($this->provider(), $connection->clinic_id);

        try {
            if ($connection->isSandbox() || $this->shouldUseMock($connection)) {
                return $this->mockProposal($request);
            }

            $token    = $this->authenticate($connection);
            $paths    = $this->endpoints($connection);
            $response = Http::timeout(config('financial.http_timeout', 15))
                ->withToken($token)
                ->post(rtrim($paths['api_base'], '/') . ($paths['proposal_path'] ?? '/propostas'), $request->toArray());

            if (!$response->successful()) {
                throw new FinancialGatewayException(
                    "Proposta falhou: HTTP {$response->status()}",
                    $this->provider(),
                    'Não foi possível enviar a proposta. Tente novamente em instantes.'
                );
            }

            $this->circuitBreaker->recordSuccess($this->provider(), $connection->clinic_id);
            $data = $response->json() ?? [];

            return new FinancingProposalResult(
                externalId: (string) ($data['id'] ?? $data['external_id'] ?? Str::uuid()),
                status: FinancingProposalStatus::Created,
                signatureUrl: $data['url_assinatura'] ?? $data['signature_url'] ?? null,
                message: $data['mensagem'] ?? null,
                raw: $data,
            );
        } catch (FinancialGatewayUnavailableException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->circuitBreaker->recordFailure($this->provider(), $connection->clinic_id);
            throw new FinancialGatewayException($e->getMessage(), $this->provider(), previous: $e);
        }
    }

    public function parseWebhookPayload(array $payload): array
    {
        $eventMap = [
            'proposta_criada'       => FinancingWebhookEventType::ProposalCreated,
            'criada'                => FinancingWebhookEventType::ProposalCreated,
            'em_analise'            => FinancingWebhookEventType::UnderReview,
            'aprovada'              => FinancingWebhookEventType::Approved,
            'recusada'              => FinancingWebhookEventType::Rejected,
            'aguardando_assinatura' => FinancingWebhookEventType::AwaitingSignature,
            'assinada'              => FinancingWebhookEventType::Signed,
            'pagamento_realizado'   => FinancingWebhookEventType::Paid,
            'cancelada'             => FinancingWebhookEventType::Cancelled,
            'liquidada'             => FinancingWebhookEventType::Settled,
        ];

        $rawEvent = strtolower((string) ($payload['evento'] ?? $payload['event'] ?? $payload['status'] ?? 'desconhecido'));
        $event    = $eventMap[$rawEvent] ?? FinancingWebhookEventType::Unknown;

        return [
            'event_type'   => $event,
            'external_id'  => (string) ($payload['proposta_id'] ?? $payload['proposal_id'] ?? $payload['id'] ?? ''),
            'signature_url'=> $payload['url_assinatura'] ?? $payload['signature_url'] ?? null,
            'net_amount'   => isset($payload['valor_liquido']) ? (float) $payload['valor_liquido'] : null,
            'fees_amount'  => isset($payload['taxas']) ? (float) $payload['taxas'] : null,
            'settled_at'   => $payload['data_liquidacao'] ?? $payload['settled_at'] ?? null,
            'raw'          => $payload,
        ];
    }

    protected function authenticate(ClinicFinancialConnection $connection, bool $recordSuccess = true): string
    {
        if ($connection->access_token && $connection->token_expires_at?->isFuture()) {
            return Crypt::decryptString($connection->access_token);
        }

        if ($this->shouldUseMock($connection)) {
            $mockToken = 'sandbox_' . Str::random(32);
            $connection->update([
                'access_token'     => Crypt::encryptString($mockToken),
                'token_expires_at' => now()->addHour(),
            ]);

            return $mockToken;
        }

        $paths = $this->endpoints($connection);
        $response = Http::timeout(config('financial.http_timeout', 15))
            ->asForm()
            ->post($paths['token_url'] ?? '', [
                'grant_type'    => 'client_credentials',
                'client_id'     => Crypt::decryptString($connection->client_id),
                'client_secret' => Crypt::decryptString($connection->client_secret),
            ]);

        if (!$response->successful()) {
            throw new FinancialGatewayException(
                "OAuth falhou: HTTP {$response->status()}",
                $this->provider(),
                'Falha na autenticação com a instituição financeira.'
            );
        }

        $data  = $response->json();
        $token = (string) ($data['access_token'] ?? '');
        $expiresIn = (int) ($data['expires_in'] ?? 3600);

        $connection->update([
            'access_token'     => Crypt::encryptString($token),
            'token_expires_at' => now()->addSeconds(max(60, $expiresIn - 60)),
        ]);

        if ($recordSuccess) {
            $this->circuitBreaker->recordSuccess($this->provider(), $connection->clinic_id);
        }

        return $token;
    }

    protected function shouldUseMock(ClinicFinancialConnection $connection): bool
    {
        return $connection->isSandbox()
            && (bool) ($connection->metadata['use_mock'] ?? true);
    }

    /** @return FinancingSimulationResult[] */
    protected function mockSimulations(FinancingSimulationRequest $request): array
    {
        $baseRate = match ($this->providerSlug()) {
            'banco_bv'    => 1.89,
            'dr_cash'     => 2.10,
            'dental_cred' => 1.95,
            'konsiga'     => 2.25,
            default       => 2.00,
        };

        $options = [];
        foreach ([6, 12, 18, 24, 36] as $months) {
            if ($months > $request->installments && $months !== $request->installments) {
                continue;
            }
            if ($months < $request->installments && $months !== 6) {
                continue;
            }

            $monthlyRate = $baseRate / 100;
            $installment = $request->amount * ($monthlyRate * pow(1 + $monthlyRate, $months)) / (pow(1 + $monthlyRate, $months) - 1);
            $total       = $installment * $months;
            $cet         = $baseRate + 0.45;

            $options[] = new FinancingSimulationResult(
                provider: $this->providerSlug(),
                providerName: $this->displayName(),
                installments: $months,
                installmentValue: round($installment, 2),
                totalAmount: round($total, 2),
                interestRate: $baseRate,
                cet: round($cet, 2),
                fees: round($total - $request->amount, 2),
                termMonths: $months,
                externalId: 'sim_' . Str::uuid(),
                raw: ['sandbox' => true],
            );
        }

        if (empty($options)) {
            $months      = $request->installments;
            $monthlyRate = $baseRate / 100;
            $installment = $request->amount * ($monthlyRate * pow(1 + $monthlyRate, $months)) / (pow(1 + $monthlyRate, $months) - 1);

            $options[] = new FinancingSimulationResult(
                provider: $this->providerSlug(),
                providerName: $this->displayName(),
                installments: $months,
                installmentValue: round($installment, 2),
                totalAmount: round($installment * $months, 2),
                interestRate: $baseRate,
                cet: round($baseRate + 0.45, 2),
                fees: round(($installment * $months) - $request->amount, 2),
                termMonths: $months,
                externalId: 'sim_' . Str::uuid(),
                raw: ['sandbox' => true],
            );
        }

        return $options;
    }

    protected function mockProposal(FinancingProposalRequest $request): FinancingProposalResult
    {
        $externalId = 'prop_' . Str::uuid();

        return new FinancingProposalResult(
            externalId: $externalId,
            status: FinancingProposalStatus::AwaitingSignature,
            signatureUrl: "https://sandbox.{$this->providerSlug()}.example/assinatura/{$externalId}",
            message: 'Proposta criada em sandbox. Assinatura ocorre no ambiente oficial da instituição.',
            raw: ['sandbox' => true],
        );
    }

    /** @return FinancingSimulationResult[] */
    protected function mapSimulationResponse(array $data): array
    {
        $proposals = $data['propostas'] ?? $data['options'] ?? [$data];
        $results   = [];

        foreach ($proposals as $item) {
            if (!is_array($item)) {
                continue;
            }
            $results[] = new FinancingSimulationResult(
                provider: $this->providerSlug(),
                providerName: $this->displayName(),
                installments: (int) ($item['parcelas'] ?? $item['installments'] ?? 0),
                installmentValue: (float) ($item['valor_parcela'] ?? $item['installment_value'] ?? 0),
                totalAmount: (float) ($item['valor_total'] ?? $item['total_amount'] ?? 0),
                interestRate: (float) ($item['taxa_juros'] ?? $item['interest_rate'] ?? 0),
                cet: (float) ($item['cet'] ?? 0),
                fees: (float) ($item['taxas'] ?? $item['fees'] ?? 0),
                termMonths: (int) ($item['prazo'] ?? $item['term_months'] ?? 0),
                externalId: (string) ($item['id'] ?? $item['external_id'] ?? ''),
                raw: $item,
            );
        }

        return $results;
    }

    protected function buildTestReport(
        ClinicFinancialConnection $connection,
        bool $success,
        array $checks,
        array $recommendations,
        ?int $responseTimeMs,
    ): IntegrationTestReport {
        $failures = collect($checks)->where('status', 'fail')->count();
        $score    = max(0, 100 - ($failures * 20));

        return new IntegrationTestReport(
            provider: $this->provider(),
            providerName: $this->displayName(),
            environment: $connection->environment,
            success: $success,
            healthScore: $score,
            checks: $checks,
            recommendations: $recommendations,
            responseTimeMs: $responseTimeMs,
            lastSyncAt: $connection->last_sync_at?->toIso8601String(),
        );
    }
}