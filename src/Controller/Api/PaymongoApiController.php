<?php

namespace App\Controller\Api;

use App\Entity\Payment;
use App\Repository\ApplicationRepository;
use App\Repository\PaymentRepository;
use App\Service\ActivityLogService;
use App\Service\NotificationService;
use App\Service\PaymongoCheckoutHandler;
use App\Service\PaymongoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class PaymongoApiController extends AbstractController
{
    public function __construct(
        private readonly PaymongoService $paymongoService,
        private readonly PaymongoCheckoutHandler $checkoutHandler,
        private readonly NotificationService $notificationService,
        private readonly ActivityLogService $activityLogService,
    ) {
    }

    /** Payment channels and required fields — call before showing the Paymongo form in the mobile app. */
    #[Route('/mobile/payments/paymongo/options', name: 'api_mobile_paymongo_options', methods: ['GET'])]
    public function paymentOptions(): JsonResponse
    {
        return $this->json([
            'success' => true,
            'data' => $this->checkoutHandler->getPaymentOptionsSchema(),
        ]);
    }

    /** Mobile + web: create Paymongo checkout link for an approved application. */
    #[Route('/mobile/applications/{id}/payments/paymongo', name: 'api_mobile_paymongo_checkout', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function mobileCheckout(
        int $id,
        Request $request,
        ApplicationRepository $applicationRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->isGranted('ROLE_TENANT')) {
            return $this->json(['success' => false, 'error' => 'Customers only'], Response::HTTP_FORBIDDEN);
        }

        $application = $applicationRepository->find($id);
        if (!$application || $application->getTenant()?->getId() !== $user->getId()) {
            return $this->json(['success' => false, 'error' => 'Application not found'], Response::HTTP_NOT_FOUND);
        }

        if ($application->getStatus() !== 'approved') {
            return $this->json(['success' => false, 'error' => 'Pay only for approved bookings'], Response::HTTP_BAD_REQUEST);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $checkoutBase = $this->resolveCheckoutBaseUrl($request, $payload);

        try {
            $result = $this->checkoutHandler->startCheckout(
                $user,
                $application,
                $payload,
                $checkoutBase,
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], Response::HTTP_SERVICE_UNAVAILABLE);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        $payment = $result['payment'];
        $amount = $payment->getAmount();

        $this->activityLogService->logEvent(
            $user,
            'MOBILE_PAYMENT',
            sprintf(
                'Paymongo checkout (%s) — PHP %s (Application #%d)',
                $result['channelLabel'],
                $amount,
                $application->getId(),
            ),
            'paymongo',
            'App\\Entity\\Payment',
            (string) $payment->getId(),
        );

        return $this->json([
            'success' => true,
            'data' => [
                'paymentId' => $payment->getId(),
                'checkoutUrl' => $result['checkoutUrl'],
                'amount' => $amount,
                'mock' => $result['mock'],
                'paymentChannel' => $result['channel'],
                'channelLabel' => $result['channelLabel'],
            ],
        ]);
    }

    /**
     * Dev-only demo checkout page (PAYMONGO_DEV_MOCK=1, no secret key).
     * Mobile opens this URL in the browser; tap Complete to mark payment paid.
     */
    #[Route('/paymongo/dev-checkout/{paymentId}', name: 'api_paymongo_dev_checkout', methods: ['GET'], requirements: ['paymentId' => '\d+'], defaults: ['_profiler' => false])]
    public function devCheckout(int $paymentId, PaymentRepository $paymentRepository): Response
    {
        if (!$this->paymongoService->isDevMock()) {
            throw $this->createNotFoundException();
        }

        $payment = $paymentRepository->find($paymentId);
        if (!$payment || !str_starts_with((string) $payment->getPaymongoLinkId(), 'mock_link_')) {
            throw $this->createNotFoundException();
        }

        $channelLabel = $this->channelLabelFromPayment($payment);

        return $this->render('paymongo/checkout.html.twig', [
            'listing' => $payment->getApplication()?->getListing()?->getName() ?? 'Rent payment',
            'amount' => number_format((float) $payment->getAmount(), 2),
            'complete_url' => $this->generateUrl('api_paymongo_dev_complete', ['paymentId' => $paymentId]),
            'channel_label' => $channelLabel,
            'payer_summary' => $this->payerSummaryFromNotes($payment->getNotes()),
        ]);
    }

    #[Route('/paymongo/dev-complete/{paymentId}', name: 'api_paymongo_dev_complete', methods: ['GET'], requirements: ['paymentId' => '\d+'], defaults: ['_profiler' => false])]
    public function devComplete(int $paymentId, PaymentRepository $paymentRepository, EntityManagerInterface $em): Response
    {
        if (!$this->paymongoService->isDevMock()) {
            throw $this->createNotFoundException();
        }

        $payment = $paymentRepository->find($paymentId);
        if (!$payment || !str_starts_with((string) $payment->getPaymongoLinkId(), 'mock_link_')) {
            throw $this->createNotFoundException();
        }

        if ($payment->getStatus() !== 'completed') {
            $payment->setStatus('completed');
            $payment->setPaidAt(new \DateTimeImmutable());
            $em->flush();

            $application = $payment->getApplication();
            if ($application?->getLandlord()) {
                $this->notificationService->notifyUser(
                    $application->getLandlord(),
                    'payment_submitted',
                    sprintf('Payment completed: PHP %s for %s', $payment->getAmount(), $application->getListing()?->getName()),
                    'Payment',
                    $payment->getId()
                );
            }
        }

        return $this->render('paymongo/complete.html.twig');
    }

    /** Paymongo webhook (link.payment.paid). */
    #[Route('/paymongo/webhook', name: 'api_paymongo_webhook', methods: ['POST'])]
    public function webhook(Request $request, PaymentRepository $paymentRepository, EntityManagerInterface $em): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $eventType = $payload['data']['attributes']['type'] ?? $payload['type'] ?? '';

        if (!in_array($eventType, ['link.payment.paid', 'payment.paid'], true)) {
            return $this->json(['received' => true]);
        }

        $linkData = $payload['data']['attributes']['data'] ?? [];
        $linkId = (string) ($linkData['id'] ?? $payload['data']['id'] ?? '');

        if ($linkId === '') {
            return $this->json(['received' => true]);
        }

        $payment = $paymentRepository->findOneBy(['paymongoLinkId' => $linkId]);
        if (!$payment || $payment->getStatus() === 'completed') {
            return $this->json(['received' => true]);
        }

        $payment->setStatus('completed');
        $payment->setPaidAt(new \DateTimeImmutable());
        $em->flush();

        $application = $payment->getApplication();
        if ($application?->getLandlord()) {
            $this->notificationService->notifyUser(
                $application->getLandlord(),
                'payment_submitted',
                sprintf('Paymongo payment completed: PHP %s for %s', $payment->getAmount(), $application->getListing()?->getName()),
                'Payment',
                $payment->getId(),
            );
        }

        return $this->json(['received' => true]);
    }

    /** @param array<string, mixed> $payload */
    private function resolveCheckoutBaseUrl(Request $request, array $payload): string
    {
        $fromApp = isset($payload['appOrigin']) ? trim((string) $payload['appOrigin']) : '';
        if ($fromApp !== '' && filter_var($fromApp, FILTER_VALIDATE_URL)) {
            return rtrim($fromApp, '/');
        }

        return rtrim($request->getSchemeAndHttpHost(), '/');
    }

    private function channelLabelFromPayment(Payment $payment): string
    {
        return match ($payment->getPaymentMethod()) {
            'paymongo_gcash' => 'GCash (online)',
            'paymongo_paymaya' => 'Maya / PayMaya (online)',
            'paymongo_card' => 'Credit / debit card',
            default => 'Paymongo',
        };
    }

    private function payerSummaryFromNotes(?string $notes): string
    {
        if (!$notes) {
            return '';
        }
        foreach (explode("\n", $notes) as $line) {
            if (str_starts_with($line, 'Payer: ')) {
                return substr($line, 7);
            }
        }

        return '';
    }
}
