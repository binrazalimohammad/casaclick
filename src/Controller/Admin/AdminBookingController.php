<?php

namespace App\Controller\Admin;

use App\Entity\Application;
use App\Repository\ApplicationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
    public function data(ApplicationRepository $applicationRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $rows = [];
        foreach ($applicationRepository->findAllOrdered() as $booking) {
            $status = (string) $booking->getStatus();
            $rows[] = [
                'id' => (int) $booking->getId(),
                'tenant' => $booking->getTenant()?->getEmail() ?? $booking->getTenant()?->getName() ?? '—',
                'listing' => $booking->getListing()?->getName() ?? '—',
                'landlord' => $booking->getLandlord()?->getEmail() ?? $booking->getLandlord()?->getName() ?? '—',
                'status' => $status,
                'statusLabel' => self::BOOKING_STATUSES[$status] ?? $status,
            ];
        }

        return new JsonResponse([
            'success' => true,
            'bookings' => $rows,
            'statuses' => self::BOOKING_STATUSES,
        ]);
    }

    /**
     * Approval is landlord-only (Applications page). Admin cannot change booking status here.
     */
    #[Route('/{id}/status', name: 'app_admin_booking_status', methods: ['POST'])]
    public function updateStatus(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->addFlash(
            'warning',
            'Booking approval is handled by the listing landlord under Applications — not by admin.',
        );

        return $this->redirectToRoute('app_admin_bookings');
    }
}
