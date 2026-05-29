<?php

namespace App\Controller\Admin;

use App\Entity\Application;
use App\Repository\ApplicationRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/admin/bookings')]
final class AdminBookingController extends AbstractController
{
    /** Customer-facing order flow + legacy booking statuses (do not remove existing keys). */
    public const BOOKING_STATUSES = [
        'pending' => 'Pending',
        'accepted' => 'Accepted',
        'approved' => 'Accepted',
        'processing' => 'Processing',
        'ready_for_pickup' => 'Ready for Pickup',
        'completed' => 'Completed',
        'refunded' => 'Refund',
        'cancelled' => 'Cancelled',
        'rejected' => 'Rejected',
    ];

    #[Route('', name: 'app_admin_bookings', methods: ['GET'])]
    public function index(ApplicationRepository $applicationRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $bookings = $applicationRepository->findAllOrdered();

        return $this->render('admin/bookings/index.html.twig', [
            'bookings' => $bookings,
            'statuses' => self::BOOKING_STATUSES,
        ]);
    }

    #[Route('/feed', name: 'app_admin_bookings_feed', methods: ['GET'])]
    public function feed(ApplicationRepository $applicationRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $meta = $applicationRepository->getGlobalSyncMeta();
        $revision = ProductRepository::buildSyncRevision($meta['count'], $meta['latestUpdatedAt']);

        return new JsonResponse([
            'success' => true,
            'revision' => $revision,
            'count' => $meta['count'],
            'serverTime' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    /** JSON rows for in-page live updates (no full reload). */
    #[Route('/data', name: 'app_admin_bookings_data', methods: ['GET'])]
    public function data(
        ApplicationRepository $applicationRepository,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $rows = [];
        foreach ($applicationRepository->findAllOrdered() as $booking) {
            $status = (string) $booking->getStatus();
            $bookingId = (int) $booking->getId();
            $rows[] = [
                'id' => $bookingId,
                'tenant' => $booking->getTenant()?->getEmail() ?? $booking->getTenant()?->getName() ?? '—',
                'listing' => $booking->getListing()?->getName() ?? '—',
                'status' => $status,
                'statusLabel' => self::BOOKING_STATUSES[$status] ?? $status,
                'csrfToken' => $csrfTokenManager->getToken('booking_status_' . $bookingId)->getValue(),
                'statusUrl' => $this->generateUrl('app_admin_booking_status', ['id' => $bookingId]),
            ];
        }

        return new JsonResponse([
            'success' => true,
            'bookings' => $rows,
            'statuses' => self::BOOKING_STATUSES,
        ]);
    }

    #[Route('/{id}/status', name: 'app_admin_booking_status', methods: ['POST'])]
    public function updateStatus(
        Application $application,
        Request $request,
        EntityManagerInterface $em,
        ActivityLogService $activityLogService,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('booking_status_' . $application->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_admin_bookings');
        }

        $status = (string) $request->request->get('status', '');
        if (!array_key_exists($status, self::BOOKING_STATUSES)) {
            $this->addFlash('error', 'Invalid booking status.');
            return $this->redirectToRoute('app_admin_bookings');
        }

        $previous = $application->getStatus();
        $application->setStatus($status);
        $application->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        $admin = $this->getUser();
        $activityLogService->logEvent(
            $admin,
            'UPDATE',
            sprintf(
                'Booking #%d status: %s → %s (listing: %s)',
                $application->getId(),
                $previous,
                $status,
                $application->getListing()?->getName() ?? '—',
            ),
            $application->getTenant()?->getEmail() ?? '',
            'App\\Entity\\Application',
            (string) $application->getId(),
        );

        $this->addFlash('success', 'Booking status updated to ' . self::BOOKING_STATUSES[$status] . '.');

        return $this->redirectToRoute('app_admin_bookings');
    }
}
