<?php

namespace App\Service;

/**
 * Validates tenant Paymongo checkout details (channel + required fields).
 */
class PaymongoPaymentDetailsValidator
{
    public const CHANNEL_GCASH = 'gcash';
    public const CHANNEL_PAYMAYA = 'paymaya';
    public const CHANNEL_CARD = 'card';

    /** @return array{
     *   channel: string,
     *   paymentMethod: string,
     *   payerName: string,
     *   payerContact: ?string,
     *   payerEmail: ?string,
     *   referenceNote: ?string,
     *   transactionId: ?string,
     *   notes: string,
     *   channelLabel: string
     * }
     */
    public function validate(array $payload): array
    {
        $channel = strtolower(trim((string) ($payload['paymentChannel'] ?? '')));
        if (!in_array($channel, [self::CHANNEL_GCASH, self::CHANNEL_PAYMAYA, self::CHANNEL_CARD], true)) {
            throw new \InvalidArgumentException(
                'Select how you will pay: GCash, Maya (online), or credit/debit card.',
            );
        }

        $payerName = trim((string) ($payload['payerName'] ?? ''));
        if (mb_strlen($payerName) < 2) {
            throw new \InvalidArgumentException('Full name (as registered on your account) is required.');
        }

        $payerContact = trim((string) ($payload['payerContact'] ?? ''));
        $payerEmail = trim((string) ($payload['payerEmail'] ?? ''));
        $referenceNote = trim((string) ($payload['referenceNote'] ?? ''));

        if (in_array($channel, [self::CHANNEL_GCASH, self::CHANNEL_PAYMAYA], true)) {
            if (!$this->isPhilippineMobile($payerContact)) {
                throw new \InvalidArgumentException(
                    'Registered mobile number is required (Philippine format: 09XXXXXXXXX).',
                );
            }
            $payerContact = $this->normalizeMobile($payerContact);
        }

        if ($channel === self::CHANNEL_CARD) {
            if (!filter_var($payerEmail, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Billing email is required for card payments.');
            }
        }

        $channelLabel = match ($channel) {
            self::CHANNEL_GCASH => 'GCash (online)',
            self::CHANNEL_PAYMAYA => 'Maya / PayMaya (online)',
            self::CHANNEL_CARD => 'Credit / debit card',
            default => $channel,
        };

        return [
            'channel' => $channel,
            'paymentMethod' => 'paymongo_' . $channel,
            'payerName' => $payerName,
            'payerContact' => $payerContact !== '' ? $payerContact : null,
            'payerEmail' => $payerEmail !== '' ? $payerEmail : null,
            'referenceNote' => $referenceNote !== '' ? $referenceNote : null,
            'transactionId' => in_array($channel, [self::CHANNEL_GCASH, self::CHANNEL_PAYMAYA], true)
                ? $payerContact
                : null,
            'notes' => $this->formatNotes($channelLabel, $payerName, $payerContact, $payerEmail, $referenceNote),
            'channelLabel' => $channelLabel,
        ];
    }

    private function isPhilippineMobile(string $value): bool
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return (bool) preg_match('/^(?:63|0)?9\d{9}$/', $digits);
    }

    private function normalizeMobile(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            return '0' . substr($digits, 2);
        }
        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            return '0' . $digits;
        }

        return $digits;
    }

    private function formatNotes(
        string $channelLabel,
        string $payerName,
        string $payerContact,
        string $payerEmail,
        string $referenceNote,
    ): string {
        $lines = [
            'Paymongo channel: ' . $channelLabel,
            'Payer: ' . $payerName,
        ];
        if ($payerContact !== '') {
            $lines[] = 'Mobile: ' . $payerContact;
        }
        if ($payerEmail !== '') {
            $lines[] = 'Billing email: ' . $payerEmail;
        }
        if ($referenceNote !== '') {
            $lines[] = 'Tenant note: ' . $referenceNote;
        }

        return implode("\n", $lines);
    }
}
