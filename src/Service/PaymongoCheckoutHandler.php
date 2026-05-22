<?php

namespace App\Service;

use App\Entity\Application;
use App\Entity\Payment;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Creates a pending Paymongo payment after validating tenant channel + requirements.
 */
class PaymongoCheckoutHandler
{
    public function __construct(
        private readonly PaymongoService $paymongoService,
        private readonly PaymongoPaymentDetailsValidator $paymentDetailsValidator,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{
     *   payment: Payment,
     *   checkoutUrl: string,
     *   mock: bool,
     *   channel: string,
     *   channelLabel: string
     * }
     */
    public function startCheckout(
        User $tenant,
        Application $application,
        array $payload,
        ?string $checkoutBaseUrl = null,
    ): array {
        if ($application->getTenant()?->getId() !== $tenant->getId()) {
            throw new \InvalidArgumentException('You can only pay for your own booking.');
        }

        if ($application->getStatus() !== 'approved') {
            throw new \InvalidArgumentException('You can only pay for approved bookings.');
        }

        if (!$this->paymongoService->isConfigured()) {
            throw new \RuntimeException('Paymongo is not available. Use manual payment or contact support.');
        }

        $details = $this->paymentDetailsValidator->validate($payload);
        $amount = (string) ($payload['amount'] ?? $application->getListing()?->getPrice() ?? '0');
        $centavos = PaymongoService::pesosToCentavos($amount);

        $payment = new Payment();
        $payment->setApplication($application);
        $payment->setAmount($amount);
        $payment->setPaymentMethod($details['paymentMethod']);
        $payment->setStatus('pending');
        $payment->setNotes($details['notes']);
        if ($details['transactionId']) {
            $payment->setTransactionId($details['transactionId']);
        }

        $this->em->persist($payment);
        $this->em->flush();

        try {
            $link = $this->paymongoService->createPaymentLink(
                $centavos,
                'CasaClick rent — ' . ($application->getListing()?->getName() ?? 'Listing'),
                sprintf('%s · App #%d · %s', $details['channelLabel'], $application->getId(), $details['payerName']),
                $payment->getId(),
                $checkoutBaseUrl,
            );
        } catch (\Throwable $e) {
            $this->em->remove($payment);
            $this->em->flush();
            throw $e;
        }

        $payment->setPaymongoLinkId($link['linkId']);
        $payment->setPaymongoCheckoutUrl($link['checkoutUrl']);
        if (!empty($link['referenceNumber'])) {
            $payment->setTransactionId($link['referenceNumber']);
        }
        $this->em->flush();

        return [
            'payment' => $payment,
            'checkoutUrl' => $link['checkoutUrl'],
            'mock' => $link['mock'] ?? false,
            'channel' => $details['channel'],
            'channelLabel' => $details['channelLabel'],
        ];
    }

    /** @return array<string, mixed> Options schema for web/mobile forms */
    public function getPaymentOptionsSchema(): array
    {
        return [
            'categories' => [
                [
                    'id' => 'online',
                    'label' => 'Online payment',
                    'description' => 'Pay with GCash or Maya e-wallet',
                    'channels' => [
                        [
                            'id' => PaymongoPaymentDetailsValidator::CHANNEL_GCASH,
                            'label' => 'GCash',
                            'requirements' => [
                                ['key' => 'payerName', 'label' => 'Full name (as on GCash account)', 'required' => true, 'type' => 'text'],
                                ['key' => 'payerContact', 'label' => 'GCash mobile number', 'required' => true, 'type' => 'phone', 'placeholder' => '09XX XXX XXXX'],
                                ['key' => 'referenceNote', 'label' => 'Note for landlord (optional)', 'required' => false, 'type' => 'text'],
                            ],
                        ],
                        [
                            'id' => PaymongoPaymentDetailsValidator::CHANNEL_PAYMAYA,
                            'label' => 'Maya / PayMaya',
                            'requirements' => [
                                ['key' => 'payerName', 'label' => 'Full name (as on Maya account)', 'required' => true, 'type' => 'text'],
                                ['key' => 'payerContact', 'label' => 'Maya mobile number', 'required' => true, 'type' => 'phone', 'placeholder' => '09XX XXX XXXX'],
                                ['key' => 'referenceNote', 'label' => 'Note for landlord (optional)', 'required' => false, 'type' => 'text'],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'card',
                    'label' => 'Credit / debit card',
                    'description' => 'Visa, Mastercard via secure Paymongo checkout',
                    'channels' => [
                        [
                            'id' => PaymongoPaymentDetailsValidator::CHANNEL_CARD,
                            'label' => 'Card',
                            'requirements' => [
                                ['key' => 'payerName', 'label' => 'Name on card', 'required' => true, 'type' => 'text'],
                                ['key' => 'payerEmail', 'label' => 'Billing email', 'required' => true, 'type' => 'email'],
                                ['key' => 'referenceNote', 'label' => 'Billing note (optional)', 'required' => false, 'type' => 'text'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
