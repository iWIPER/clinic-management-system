<?php

namespace App\Services;

use Google_Client;
use Google_Service_Drive;
use Google_Service_Oauth2;
use App\Exceptions\GoogleDriveReauthRequiredException;
use App\Models\Clinic;
use App\Models\ClinicStorageConnection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Fase C1.2.2 — primeira extração arquitetural do GoogleDriveService.
 * Responde só por "como autenticar e obter um Google Drive autenticado para
 * uma clínica" — nunca por pasta/upload/foto/quota/reparo (isso continua em
 * GoogleDriveService, que passa a consumir esta classe).
 *
 * Sem estado de "clínica atual": cada método recebe a Clinic explicitamente
 * como parâmetro, nunca depende de uma chamada anterior na mesma instância.
 * O único estado interno é o Google_Client (client OAuth configurado uma
 * vez no construtor) — esta classe é agora a única dona da sua criação e
 * configuração; GoogleDriveService não constrói mais nenhum Google_Client
 * próprio.
 */
class GoogleDriveAuthService
{
    private Google_Client $client;

    public function __construct()
    {
        $this->client = new Google_Client();
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setRedirectUri(config('services.google.redirect'));
        $this->client->addScope(Google_Service_Drive::DRIVE_FILE);
        $this->client->addScope(Google_Service_Oauth2::USERINFO_EMAIL);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
    }

    /**
     * Seam de teste (fase C1.2.1) — movida pra cá porque o Google_Client
     * agora só existe aqui. GoogleDriveService::useHttpClientForTesting()
     * continua existindo como wrapper, pra não quebrar nenhum teste já
     * escrito contra a classe original (folders/upload/photos/quota/etc.,
     * que não são movidos nesta fase).
     */
    public function useHttpClientForTesting(\GuzzleHttp\ClientInterface $http): void
    {
        $this->client->setHttpClient($http);
    }

    public function getAuthUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    public function exchangeCode(string $code): array
    {
        return $this->callOAuthEndpoint(fn () => $this->client->fetchAccessTokenWithAuthCode($code));
    }

    /**
     * Return the Google account email from a freshly issued token array.
     * Called once during the OAuth callback — no extra HTTP round-trip needed
     * because the id_token is already in the token response.
     */
    public function fetchEmailFromToken(array $token): ?string
    {
        try {
            $payload = $this->client->verifyIdToken($token['id_token'] ?? null);

            return $payload['email'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Return an authenticated Google_Service_Drive for the given clinic.
     * Restores the cached access token and refreshes proactively when expired.
     *
     * @throws GoogleDriveReauthRequiredException when there is no usable refresh token.
     */
    public function getDriveForClinic(Clinic $clinic): Google_Service_Drive
    {
        $connection = $clinic->storageConnection;

        if (!$connection || !$connection->refresh_token) {
            throw new GoogleDriveReauthRequiredException($clinic);
        }

        $tokenRestored = false;

        if ($connection->access_token) {
            try {
                $cached = json_decode(Crypt::decryptString($connection->access_token), true);
                $this->client->setAccessToken($cached);
                $tokenRestored = true;
            } catch (\Throwable) {
                // Corrupted cache — will refresh below
            }
        }

        if (!$tokenRestored || $this->client->isAccessTokenExpired()) {
            $this->forceRefreshAccessToken($clinic);
        }

        return new Google_Service_Drive($this->client);
    }

    /**
     * Attempt to renew the Drive connection using only the stored refresh token,
     * without opening the Google consent screen.
     *
     * Used by the "Reconectar Drive" button: the OAuth flow is only triggered
     * when this returns false (Caso 3 do fluxo de reconexão).
     */
    public function tryRenewConnection(Clinic $clinic): bool
    {
        $connection = $clinic->storageConnection;

        if (!$connection || !$connection->refresh_token) {
            return false;
        }

        try {
            $this->forceRefreshAccessToken($clinic);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Force a refresh-token exchange, bypassing the local expiry cache.
     * Called both proactively (cached token expired) and reactively, from
     * GoogleDriveService::callDrive() (a live Drive call came back with an
     * auth error) — por isso é público: é o único método desta classe
     * chamado de fora dela, e propositalmente recebe Clinic explícito em
     * vez de depender de algum estado de "clínica atual".
     *
     * @throws GoogleDriveReauthRequiredException when the refresh token itself is invalid.
     */
    public function forceRefreshAccessToken(Clinic $clinic): void
    {
        $connection = $clinic->storageConnection;

        if (!$connection || !$connection->refresh_token) {
            throw new GoogleDriveReauthRequiredException($clinic);
        }

        try {
            $refreshToken = Crypt::decryptString($connection->refresh_token);
        } catch (\Throwable) {
            $this->clearInvalidTokens($connection);
            throw new GoogleDriveReauthRequiredException($clinic);
        }

        $newToken = $this->callOAuthEndpoint(fn () => $this->client->fetchAccessTokenWithRefreshToken($refreshToken));

        if (isset($newToken['error'])) {
            if (in_array($newToken['error'], ['invalid_grant', 'invalid_client'], true)) {
                $this->clearInvalidTokens($connection);
                throw new GoogleDriveReauthRequiredException($clinic);
            }

            throw new \RuntimeException(
                'Falha ao renovar token do Google Drive: '
                . ($newToken['error_description'] ?? $newToken['error'])
            );
        }

        $connection->update([
            'access_token' => Crypt::encryptString(json_encode($this->client->getAccessToken())),
            'expires_at'   => now()->addSeconds($newToken['expires_in'] ?? 3600),
        ]);
    }

    /**
     * Refresh token confirmed dead — drop only the token material and mark
     * the connection as needing reauth. Account config (google_email,
     * drive_root_folder_id, provider) is preserved (Caso 2).
     */
    private function clearInvalidTokens(ClinicStorageConnection $connection): void
    {
        $connection->update([
            'access_token'  => null,
            'refresh_token' => null,
            'expires_at'    => null,
            'status'        => 'reauth_required',
        ]);

        Log::warning('Refresh Token do Google Drive inválido — tokens removidos, reautenticação necessária.', [
            'clinic_id' => $connection->clinic_id,
        ]);
    }

    /**
     * Fase C1.2.1.1 — ponto único de normalização de erro pros dois endpoints
     * de troca de token OAuth (fetchAccessTokenWithAuthCode/
     * fetchAccessTokenWithRefreshToken). Esses dois métodos do Google_Client
     * NÃO passam por REST::execute() (o wrapper que converte erro HTTP em
     * Google_Service_Exception pras chamadas normais de API) — eles usam o
     * client Guzzle bruto, então uma resposta 4xx/5xx do Google (ex.:
     * invalid_grant, sempre HTTP 400 pela RFC 6749 §5.2) faz o middleware
     * http_errors do Guzzle lançar ClientException/ServerException em vez de
     * devolver o array {"error": ...} que o resto do código sempre esperou.
     *
     * Só traduzimos de volta pro formato esperado quando o corpo da resposta
     * é realmente um erro OAuth no formato do Google — sem resposta HTTP
     * nenhuma (timeout, DNS, conexão recusada) ou uma resposta de erro sem
     * esse formato não é um erro OAuth conhecido, então sobe cru de
     * propósito: não é invalid_grant, não deve virar reauth_required.
     */
    private function callOAuthEndpoint(callable $fetch): array
    {
        try {
            return $fetch();
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $response = $e->getResponse();
            if ($response === null) {
                throw $e;
            }

            $body = json_decode((string) $response->getBody(), true);
            if (is_array($body) && isset($body['error'])) {
                return $body;
            }

            throw $e;
        }
    }
}
