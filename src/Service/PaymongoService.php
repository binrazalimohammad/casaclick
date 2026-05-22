<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Paymongo Links API — GCash, card, etc. via hosted checkout.
 * Set PAYMONGO_SECRET_KEY in .env.local (sk_test_... or sk_live_...).
 * With PAYMONGO_DEV_MOCK=1 and an empty key, dev returns a local demo checkout URL.
 */
class PaymongoService
{
    private const API_BASE = 'https://api.paymongo.com/v1';

    private readonly HttpClientInterface $httpClient;

    private readonly string $secretKey;

    private readonly bool $devMock;

    private readonly string $appBaseUrl;

    public function __construct(
        HttpClientInterface $httpClient,
        string $secretKey = '',
        bool $devMock = false,
        string $appBaseUrl = 'http://127.0.0.1:8000',
    ) {
        $this->httpClient = $httpClient;
        $this->secretKey = trim($secretKey);
        $this->devMock = $devMock;
        $this->appBaseUrl = rtrim($appBaseUrl, '/');
    }

    public function isConfigured(): bool
    {
        if ($this->hasRealSecret()) {
            return true;
        }

        return $this->devMock;
    }

    public function isDevMock(): bool
    {
        return $this->devMock && !$this->hasRealSecret();
    }

    private function hasRealSecret(): bool
    {
        return $this->secretKey !== ''
            && !str_contains($this->secretKey, 'REPLACE')
            && !str_contains($this->secretKey, 'paste_your');
    }

    /**
     * @return array{linkId: string, checkoutUrl: string, referenceNumber: ?string, mock: bool}
     */
    public function createPaymentLink(
        int $amountCentavos,
        string $description,
        string $remarks = '',
        ?int $paymentId = null,
        ?string $checkoutBaseUrl = null,
    ): array {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Paymongo is not configured. Add PAYMONGO_SECRET_KEY to .env.local or enable PAYMONGO_DEV_MOCK=1');
        }

        if ($amountCentavos < 100) {
            throw new \InvalidArgumentException('Amount must be at least 100 centavos (₱1)');
        }

        if ($this->isDevMock()) {
            if ($paymentId === null) {
                throw new \InvalidArgumentException('Payment id is required for dev mock checkout');
            }

            $base = rtrim($checkoutBaseUrl ?? $this->appBaseUrl, '/');
            $linkId = 'mock_link_' . $paymentId;

            return [
                'linkId' => $linkId,
                'checkoutUrl' => $base . '/api/paymongo/dev-checkout/' . $paymentId,
                'referenceNumber' => 'MOCK-' . $paymentId,
                'mock' => true,
            ];
        }

        $response = $this->httpClient->request('POST', self::API_BASE . '/links', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->secretKey . ':'),
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'data' => [
                    'attributes' => [
                        'amount' => $amountCentavos,
                        'description' => mb_substr($description, 0, 255),
                        'remarks' => mb_substr($remarks, 0, 255),
                    ],
                ],
            ],
        ]);

        $body = $response->toArray(false);
        if ($response->getStatusCode() >= 400) {
            $err = $body['errors'][0]['detail'] ?? $body['errors'][0]['code'] ?? 'Paymongo request failed';
            throw new \RuntimeException(is_string($err) ? $err : 'Paymongo request failed');
        }

        $attrs = $body['data']['attributes'] ?? [];
        $checkoutUrl = (string) ($attrs['checkout_url'] ?? $attrs['redirect']['checkout_url'] ?? '');
        $linkId = (string) ($body['data']['id'] ?? '');

        if ($checkoutUrl === '' || $linkId === '') {
            throw new \RuntimeException('Paymongo did not return a checkout URL');
        }

        return [
            'linkId' => $linkId,
            'checkoutUrl' => $checkoutUrl,
            'referenceNumber' => isset($attrs['reference_number']) ? (string) $attrs['reference_number'] : null,
            'mock' => false,
        ];
    }

    /** Amount in PHP pesos → centavos for Paymongo. */
    public static function pesosToCentavos(float|string $pesos): int
    {
        return (int) round((float) $pesos * 100);
    }
}
