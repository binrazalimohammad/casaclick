<?php

namespace App\Controller\Api;

use App\Entity\Application;
use App\Entity\Payment;
use App\Entity\User;
use App\Repository\ApplicationRepository;
use App\Repository\PaymentRepository;
use App\Repository\ProductRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Customer (tenant / end-user) REST API. Requires JWT.
 * Staff, admin, and landlord accounts receive 403 — they use the web dashboard or landlord flows.
 */
#[Route('/api/mobile/customer', name: 'api_mobile_customer_')]
final class CustomerApiController extends AbstractController
{
    public function __construct(
        private NotificationService $notificationService,
    ) {
    }

    #[Route('/profile', name: 'profile_get', methods: ['GET'])]
    public function profileGet(): JsonResponse
    {
        $user = $this->customerUserOrError();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        return $this->json([
            'success' => true,
            'data' => $this->serializeUser($user),
        ]);
    }

    #[Route('/profile', name: 'profile_patch', methods: ['PATCH'])]
    public function profilePatch(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->customerUserOrError();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $errors = [];
        $updated = [];

        if (array_key_exists('name', $payload)) {
            $name = trim((string) $payload['name']);
            if (strlen($name) < 2) {
                $errors[] = 'Name must be at least 2 characters';
            } else {
                $user->setName($name);
                $updated[] = 'name';
            }
        }

        if ($updated === [] && $errors === []) {
            return $this->json([
                'success' => false,
                'errors' => ['No updatable fields supplied (supported: name)'],
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($errors !== []) {
            return $this->json(['success' => false, 'errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'data' => $this->serializeUser($user),
            'meta' => ['updated' => $updated],
        ]);
    }

    /**
     * List all rental applications (bookings) for the authenticated customer.
     */
    #[Route('/bookings', name: 'bookings_list', methods: ['GET'])]
    public function bookingsList(ApplicationRepository $applicationRepository): JsonResponse
    {
        $user = $this->customerUserOrError();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $applications = $applicationRepository->findByTenant($user);
        $data = array_map(fn (Application $a) => $this->serializeApplicationSummary($a), $applications);

        return $this->json([
            'success' => true,
            'data' => $data,
            'meta' => ['count' => count($data)],
        ], Response::HTTP_OK, [], ['json_encode_options' => JSON_UNESCAPED_SLASHES]);
    }

    /**
     * Create a booking (application) for an approved listing.
     *
     * Body: { "listingId": number, "message": "optional note" }
     */
    #[Route('/bookings', name: 'bookings_create', methods: ['POST'])]
    public function bookingsCreate(
        Request $request,
        ProductRepository $productRepository,
        ApplicationRepository $applicationRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $user = $this->customerUserOrError();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $listingId = $payload['listingId'] ?? $payload['listing_id'] ?? null;

        if (!is_numeric($listingId)) {
            return $this->json([
                'success' => false,
                'errors' => ['listingId is required and must be a number'],
            ], Response::HTTP_BAD_REQUEST);
        }

        $listing = $productRepository->findApprovedById((int) $listingId);
        if (!$listing) {
            return $this->json([
                'success' => false,
                'error' => 'Listing not found or not available',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($applicationRepository->isListingOccupied($listing)) {
            return $this->json([
                'success' => false,
                'error' => 'This listing is already occupied.',
            ], Response::HTTP_CONFLICT);
        }

        if ($listing->getCreatedBy() && $listing->getCreatedBy()->getId() === $user->getId()) {
            return $this->json([
                'success' => false,
                'error' => 'You cannot apply to your own listing.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $existing = $em->getRepository(Application::class)
            ->createQueryBuilder('a')
            ->where('a.listing = :listing')
            ->andWhere('a.tenant = :tenant')
            ->setParameter('listing', $listing)
            ->setParameter('tenant', $user)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existing) {
            return $this->json([
                'success' => false,
                'error' => 'You have already applied for this listing.',
            ], Response::HTTP_CONFLICT);
        }

        $application = new Application();
        $application->setListing($listing);
        $application->setTenant($user);
        $application->setLandlord($listing->getCreatedBy());
        $application->setStatus('pending');
        $message = isset($payload['message']) ? (string) $payload['message'] : '';
        $application->setMessage($message !== '' ? $message : null);

        $em->persist($application);
        $em->flush();

        if ($listing->getCreatedBy()) {
            $this->notificationService->notifyUser(
                $listing->getCreatedBy(),
                'application_submitted',
                sprintf('Tenant %s has applied for your listing: %s', $user->getName(), $listing->getName()),
                'Application',
                $application->getId()
            );
        }

        return $this->json([
            'success' => true,
            'data' => $this->serializeApplicationDetail($application),
            'meta' => ['message' => 'Booking submitted. Landlord will review your application.'],
        ], Response::HTTP_CREATED, [], ['json_encode_options' => JSON_UNESCAPED_SLASHES]);
    }

    #[Route('/bookings/{id}', name: 'bookings_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function bookingsShow(int $id, ApplicationRepository $applicationRepository): JsonResponse
    {
        $user = $this->customerUserOrError();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $application = $applicationRepository->findOneByTenantAndId($user, $id);
        if (!$application) {
            return $this->json([
                'success' => false,
                'error' => 'Booking not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => $this->serializeApplicationDetail($application),
        ], Response::HTTP_OK, [], ['json_encode_options' => JSON_UNESCAPED_SLASHES]);
    }

    /**
     * Confirmed rental flow: approved or completed applications (useful as "orders" for demo).
     */
    #[Route('/orders', name: 'orders_list', methods: ['GET'])]
    public function ordersList(ApplicationRepository $applicationRepository): JsonResponse
    {
        $user = $this->customerUserOrError();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $applications = $applicationRepository->findByTenantAndStatuses($user, ['approved', 'completed']);
        $data = array_map(fn (Application $a) => $this->serializeApplicationSummary($a), $applications);

        return $this->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'count' => count($data),
                'note' => 'Orders are bookings with status approved or completed. Same records appear in bookings with filters.',
            ],
        ], Response::HTTP_OK, [], ['json_encode_options' => JSON_UNESCAPED_SLASHES]);
    }

    #[Route('/payments', name: 'payments_list', methods: ['GET'])]
    public function paymentsList(PaymentRepository $paymentRepository): JsonResponse
    {
        $user = $this->customerUserOrError();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $payments = $paymentRepository->findByTenant($user);
        $data = array_map(fn (Payment $p) => $this->serializePayment($p), $payments);

        return $this->json([
            'success' => true,
            'data' => $data,
            'meta' => ['count' => count($data)],
        ], Response::HTTP_OK, [], ['json_encode_options' => JSON_UNESCAPED_SLASHES]);
    }

    /**
     * Submit a rent payment for an approved application (record / intent; landlord confirms in web app).
     *
     * Body: { "applicationId": number, "amount"?: string, "paymentMethod": string, "notes"?: string }
     */
    #[Route('/payments', name: 'payments_create', methods: ['POST'])]
    public function paymentsCreate(
        Request $request,
        ApplicationRepository $applicationRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $user = $this->customerUserOrError();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $applicationId = $payload['applicationId'] ?? $payload['application_id'] ?? null;

        if (!is_numeric($applicationId)) {
            return $this->json([
                'success' => false,
                'errors' => ['applicationId is required and must be a number'],
            ], Response::HTTP_BAD_REQUEST);
        }

        $application = $applicationRepository->findOneByTenantAndId($user, (int) $applicationId);
        if (!$application) {
            return $this->json([
                'success' => false,
                'error' => 'Application not found',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($application->getStatus() !== 'approved') {
            return $this->json([
                'success' => false,
                'error' => 'You can only submit payments for approved bookings.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $allowedMethods = ['cash', 'bank_transfer', 'gcash', 'paymaya', 'credit_card'];
        $method = isset($payload['paymentMethod']) ? (string) $payload['paymentMethod'] : '';
        if (!in_array($method, $allowedMethods, true)) {
            return $this->json([
                'success' => false,
                'errors' => [
                    'paymentMethod must be one of: '.implode(', ', $allowedMethods),
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        $listing = $application->getListing();
        $defaultAmount = $listing ? (string) $listing->getPrice() : '0.00';
        $amountStr = isset($payload['amount']) ? (string) $payload['amount'] : $defaultAmount;
        if (!is_numeric($amountStr) || (float) $amountStr <= 0) {
            return $this->json([
                'success' => false,
                'errors' => ['amount must be a positive number'],
            ], Response::HTTP_BAD_REQUEST);
        }

        $payment = new Payment();
        $payment->setApplication($application);
        $payment->setAmount(number_format((float) $amountStr, 2, '.', ''));
        $payment->setPaymentMethod($method);
        $payment->setStatus('pending');
        if (!empty($payload['notes'])) {
            $payment->setNotes((string) $payload['notes']);
        }

        $em->persist($payment);
        $em->flush();

        if ($application->getLandlord()) {
            $this->notificationService->notifyUser(
                $application->getLandlord(),
                'payment_submitted',
                sprintf(
                    'Tenant %s has submitted a payment of ₱%s for application: %s',
                    $user->getName(),
                    number_format((float) $payment->getAmount(), 2),
                    $application->getListing()?->getName() ?? 'listing'
                ),
                'Payment',
                $payment->getId()
            );
        }

        return $this->json([
            'success' => true,
            'data' => $this->serializePayment($payment),
            'meta' => ['message' => 'Payment recorded as pending. Landlord will confirm in the dashboard.'],
        ], Response::HTTP_CREATED, [], ['json_encode_options' => JSON_UNESCAPED_SLASHES]);
    }

    private function customerUserOrError(): User|JsonResponse
    {
        $u = $this->getUser();
        if (!$u instanceof User) {
            return $this->json([
                'success' => false,
                'error' => 'Unauthorized',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $primary = $u->getPrimaryRole();
        if (in_array($primary, ['ROLE_ADMIN', 'ROLE_STAFF', 'ROLE_LANDLORD'], true)) {
            return $this->json([
                'success' => false,
                'error' => 'This Customer API is only for tenant/customer accounts. Use the web dashboard or staff tools.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $u;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'roles' => $user->getRoles(),
            'primaryRole' => $user->getPrimaryRole(),
            'emailVerified' => $user->isEmailVerified(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeApplicationSummary(Application $a): array
    {
        $listing = $a->getListing();

        return [
            'id' => $a->getId(),
            'status' => $a->getStatus(),
            'message' => $a->getMessage(),
            'createdAt' => $a->getCreatedAt()->format('c'),
            'updatedAt' => $a->getUpdatedAt()?->format('c'),
            'listing' => $listing ? [
                'id' => $listing->getId(),
                'name' => $listing->getName(),
                'price' => $listing->getPrice(),
                'image' => $listing->getImage() ? '/uploads/images/'.$listing->getImage() : null,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeApplicationDetail(Application $a): array
    {
        $row = $this->serializeApplicationSummary($a);
        $landlord = $a->getLandlord();
        $row['landlord'] = $landlord ? ['id' => $landlord->getId(), 'name' => $landlord->getName()] : null;
        $row['payments'] = array_map(fn (Payment $p) => $this->serializePayment($p), $a->getPayments()->toArray());

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePayment(Payment $p): array
    {
        $app = $p->getApplication();

        return [
            'id' => $p->getId(),
            'applicationId' => $app?->getId(),
            'amount' => $p->getAmount(),
            'status' => $p->getStatus(),
            'paymentMethod' => $p->getPaymentMethod(),
            'transactionId' => $p->getTransactionId(),
            'notes' => $p->getNotes(),
            'createdAt' => $p->getCreatedAt()->format('c'),
            'paidAt' => $p->getPaidAt()?->format('c'),
            'listingName' => $app?->getListing()?->getName(),
        ];
    }
}
