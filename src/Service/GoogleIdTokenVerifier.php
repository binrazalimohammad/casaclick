<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Verifies Google Sign-In ID tokens (React Native / mobile) via Google's tokeninfo endpoint.
 * Audience must match a configured OAuth Web client ID (same as webClientId in the app).
 */
class GoogleIdTokenVerifier
{
    private const TOKENINFO_URL = 'https://oauth2.googleapis.com/tokeninfo';

    /** @var list<string> */
    private array $allowedClientIds;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        string $primaryClientId,
        ?string $mobileWebClientId = null,
    ) {
        $ids = array_filter(array_map('trim', [
            $primaryClientId,
            $mobileWebClientId ?? '',
        ]));
        $this->allowedClientIds = array_values(array_unique($ids));
    }

    /**
     * @return array{sub: string, email: string, name: ?string, picture: ?string, aud: string}
     */
    public function verify(string $idToken): array
    {
        if ($idToken === '') {
            throw new \InvalidArgumentException('ID token is required');
        }

        if ($this->allowedClientIds === []) {
            throw new \RuntimeException('Google OAuth client ID is not configured on the server');
        }

        $response = $this->httpClient->request('GET', self::TOKENINFO_URL, [
            'query' => ['id_token' => $idToken],
        ]);

        $status = $response->getStatusCode();
        $payload = $response->toArray(false);

        if ($status !== 200) {
            $message = is_string($payload['error_description'] ?? null)
                ? $payload['error_description']
                : (is_string($payload['error'] ?? null) ? $payload['error'] : 'Invalid Google token');
            throw new \InvalidArgumentException($message);
        }

        $aud = (string) ($payload['aud'] ?? '');
        if (!in_array($aud, $this->allowedClientIds, true)) {
            throw new \InvalidArgumentException('Google token audience does not match this app');
        }

        $email = (string) ($payload['email'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Google did not provide a valid email address');
        }

        $verified = $payload['email_verified'] ?? false;
        if ($verified !== true && $verified !== 'true' && $verified !== '1') {
            throw new \InvalidArgumentException('Google email is not verified');
        }

        $exp = isset($payload['exp']) ? (int) $payload['exp'] : 0;
        if ($exp > 0 && $exp < time()) {
            throw new \InvalidArgumentException('Google token has expired');
        }

        return [
            'sub' => (string) ($payload['sub'] ?? ''),
            'email' => $email,
            'name' => isset($payload['name']) ? (string) $payload['name'] : null,
            'picture' => isset($payload['picture']) ? (string) $payload['picture'] : null,
            'aud' => $aud,
        ];
    }
}
