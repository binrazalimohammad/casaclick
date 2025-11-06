<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\TenantRepository;
use App\Repository\LandlordRepository;
use App\Repository\ProductRepository;

final class ProductoverviewController extends AbstractController
{
    #[Route('/productoverview', name: 'app_productoverview')]
    public function index(TenantRepository $tenantRepository, LandlordRepository $landlordRepository, ProductRepository $productRepository): Response
    {
        $tenants = $tenantRepository->findAll();
        $landlords = $landlordRepository->findAll();
        $products = $productRepository->findBy([], ['id' => 'DESC'], 20);

        return $this->render('productoverview/index.html.twig', [
            'tenants' => $tenants,
            'landlords' => $landlords,
            'products' => $products,
        ]);
    }
}
