<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Repository\ActivityLogRepository;
use App\Repository\NotificationRepository;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        Request $request,
        ProductRepository $productRepository,
        UserRepository $userRepository,
        ActivityLogRepository $activityLogRepository,
        NotificationRepository $notificationRepository,
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        $user = $this->getUser();
        $notifications = $notificationRepository->findByUser($user, 5);
        $unreadCount = $notificationRepository->countUnreadByUser($user);

        $products = $productRepository->findBy([], ['id' => 'DESC'], 20);
        $stats = [
            'totalProperties' => $productRepository->getTotalCount(),
            'totalRevenue' => $productRepository->getTotalPriceSum(),
            'totalUsers' => $userRepository->countAll(),
            'totalLandlords' => $userRepository->countByRole('ROLE_LANDLORD'),
        ];

        $selectedId = $request->query->getInt('id', 0);
        $featured = null;
        if ($selectedId > 0) {
            $featured = $productRepository->find($selectedId);
        }
        if (!$featured && count($products) > 0) {
            $featured = $products[0];
        }

        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
            'products' => $products,
            'featured' => $featured,
            'stats' => $stats,
            'recentLogs' => $activityLogRepository->findRecent(5),
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }
}
