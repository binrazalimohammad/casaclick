<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\ProductRepository;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(Request $request, ProductRepository $productRepository): Response
    {
        $products = $productRepository->findBy([], ['id' => 'DESC'], 20);
        $stats = [
            'totalProperties' => $productRepository->getTotalCount(),
            'totalRevenue' => $productRepository->getTotalPriceSum(),
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
            'products' => $products,
            'featured' => $featured,
            'stats' => $stats,
        ]);
    }
}
