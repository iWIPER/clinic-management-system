<?php

namespace Tests\Support;

use Firebase\JWT\JWT;

/**
 * Gera um par de chaves RSA + um id_token real assinado (RS256) e o JWKS
 * correspondente, pra testar GoogleDriveService::fetchEmailFromToken() com
 * verificação de assinatura de verdade (via Firebase\JWT, a mesma biblioteca
 * que Google_Client::verifyIdToken() usa internamente) — sem nenhuma chamada
 * de rede real ao endpoint de certs do Google.
 */
class FakeGoogleIdToken
{
    public static function issue(string $email, string $audience, string $kid = 'test-kid-1'): array
    {
        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'config'           => self::opensslConfigPath(),
        ]);
        if ($res === false) {
            throw new \RuntimeException('Falha ao gerar par de chaves RSA para o teste: ' . openssl_error_string());
        }
        openssl_pkey_export($res, $privateKeyPem, null, ['config' => self::opensslConfigPath()]);
        $details = openssl_pkey_get_details($res);
        $publicKeyDetails = $details['rsa'];

        $now = time();
        $payload = [
            'iss'   => 'https://accounts.google.com',
            'aud'   => $audience,
            'sub'   => '1234567890',
            'email' => $email,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];

        $jwt = JWT::encode($payload, $privateKeyPem, 'RS256', $kid);

        $jwk = [
            'kty' => 'RSA',
            'alg' => 'RS256',
            'use' => 'sig',
            'kid' => $kid,
            'n'   => self::base64UrlEncode($publicKeyDetails['n']),
            'e'   => self::base64UrlEncode($publicKeyDetails['e']),
        ];

        return ['jwt' => $jwt, 'jwks' => ['keys' => [$jwk]]];
    }

    /**
     * PHP no Windows (Laragon) frequentemente não resolve OPENSSL_CONF
     * sozinho, e openssl_pkey_new() falha silenciosamente sem isso. Procura
     * o openssl.cnf ao lado do próprio PHP em uso; em ambientes onde a
     * OpenSSL já resolve sozinha (Linux/CI), retorna null e o parâmetro é
     * simplesmente ignorado pela extensão.
     */
    private static function opensslConfigPath(): ?string
    {
        $candidate = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';

        return is_file($candidate) ? $candidate : null;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
