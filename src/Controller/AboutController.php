<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AboutController extends AbstractController
{
    #[Route('/about', name: 'app_about')]
    public function index(): Response
    {
        $team = [
            ['name' => 'Maria Santos', 'position' => 'CEO & Co-Founder', 'bio' => '15+ years in real estate. Passionate about making housing accessible.'],
            ['name' => 'John Davis', 'position' => 'CTO', 'bio' => 'Tech innovator building the future of property management.'],
            ['name' => 'Sarah Lee', 'position' => 'Head of Operations', 'bio' => 'Ensures every listing meets our quality standards.'],
            ['name' => 'Michael Chen', 'position' => 'Head of Customer Success', 'bio' => 'Dedicated to helping tenants and landlords succeed.'],
        ];

        return $this->render('about/index.html.twig', [
            'team' => $team,
        ]);
    }
}
