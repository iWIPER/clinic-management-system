<?php

namespace Tests\Support;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * Fase C1.2.1 — transporte HTTP fake para exercitar a lógica REAL de
 * GoogleDriveService (via Google_Client::setHttpClient(), injetado através
 * do seam useHttpClientForTesting()) sem nenhuma chamada de rede real.
 *
 * Cada teste registra rotas (method + matcher de URL/corpo → resposta
 * enlatada) na ordem que quiser — o handler resolve por correspondência de
 * regra, não por fila estrita, então a ordem de registro não precisa bater
 * com a ordem exata de chamadas. Toda requisição feita é logada em
 * requestLog() para os testes que precisam verificar contagem exata de
 * tentativas (ex.: retry de callDrive()).
 */
class GoogleDriveFakeHttp
{
    /** @var array<int, array{match: callable(RequestInterface): bool, respond: callable(RequestInterface): Response}> */
    private array $routes = [];

    /** @var array<int, array{method: string, url: string, body: string}> */
    private array $log = [];

    public function on(callable $match, callable $respond): static
    {
        $this->routes[] = ['match' => $match, 'respond' => $respond];

        return $this;
    }

    /**
     * Atalho comum: casa por método HTTP + substring no path da URL.
     */
    public function onPath(string $method, string $pathContains, callable $respond): static
    {
        return $this->on(
            fn (RequestInterface $r) => strtoupper($r->getMethod()) === strtoupper($method)
                && str_contains((string) $r->getUri()->getPath(), $pathContains),
            $respond
        );
    }

    public function client(): Client
    {
        $handler = function (RequestInterface $request, array $options) {
            $body = (string) $request->getBody();
            $this->log[] = [
                'method' => $request->getMethod(),
                'url'    => (string) $request->getUri(),
                'body'   => $body,
            ];

            foreach ($this->routes as $route) {
                if (($route['match'])($request)) {
                    return Create::promiseFor(($route['respond'])($request));
                }
            }

            return Create::rejectionFor(new \RuntimeException(
                'GoogleDriveFakeHttp: nenhuma rota registrada para ' . $request->getMethod() . ' ' . $request->getUri()
            ));
        };

        // Google_Client manipula o handler como uma pilha nomeada (push/remove
        // de middleware de auth) — um Closure cru não tem esses métodos.
        return new Client(['handler' => HandlerStack::create($handler)]);
    }

    /** @return array<int, array{method: string, url: string, body: string}> */
    public function requestLog(): array
    {
        return $this->log;
    }

    public function countRequestsToPath(string $pathContains): int
    {
        return count(array_filter($this->log, fn ($r) => str_contains($r['url'], $pathContains)));
    }

    public static function json(array $body, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
    }

    public static function raw(string $body, int $status = 200, array $headers = []): Response
    {
        return new Response($status, $headers, $body);
    }

    /**
     * Corpo de erro no formato padrão da API do Google — o que
     * GoogleDriveService::isAuthError() de fato inspeciona (code + reason).
     */
    public static function googleError(int $status, string $reason, string $message = 'Error'): Response
    {
        return self::json([
            'error' => [
                'errors'  => [['domain' => 'global', 'reason' => $reason, 'message' => $message]],
                'code'    => $status,
                'message' => $message,
            ],
        ], $status);
    }

    public static function driveFile(string $id, array $extra = []): array
    {
        return array_merge(['kind' => 'drive#file', 'id' => $id], $extra);
    }

    public static function fileList(array $files, ?string $nextPageToken = null): array
    {
        return array_filter([
            'kind'          => 'drive#fileList',
            'files'         => $files,
            'nextPageToken' => $nextPageToken,
        ], fn ($v) => $v !== null);
    }

    /**
     * 'created' é essencial — Google_Client::isAccessTokenExpired() considera
     * o token sempre expirado sem esse campo (não confia só no expires_at do
     * nosso banco), forçando um refresh a cada chamada mesmo quando o teste
     * não espera nenhum.
     */
    public static function tokenResponse(array $overrides = []): array
    {
        return array_merge([
            'access_token'  => 'fake-access-token-' . uniqid(),
            'expires_in'    => 3600,
            'refresh_token' => 'fake-refresh-token-' . uniqid(),
            'scope'         => 'https://www.googleapis.com/auth/drive.file',
            'token_type'    => 'Bearer',
            'created'       => time(),
        ], $overrides);
    }
}
