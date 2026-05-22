<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AboutController extends AbstractController
{
    #[Route('/about', name: 'app_about')]
    public function index(ProductRepository $productRepository): Response
    {
        // Team social links: set full URLs (LinkedIn/Facebook/Instagram) and email as 'user@example.com' or 'mailto:user@example.com'.
        // If all are empty, the About page shows a dashed “contact” icon linking to the contact form.
        $team = [
            [
                'name' => 'Michael Dacquiado',
                'position' => 'CEO & Founder',
                'bio' => 'Leading CasaClick with a focus on trust, transparency, and verified listings for tenants and landlords.',
                'photo' => 'uploads/images/ceo-founder.png',
                'social' => [
                    'linkedin' => '',
                    'facebook' => '',
                    'instagram' => '',
                    'email' => '',
                ],
            ],
            [
                'name' => 'Jona Bohol',
                'position' => 'CTO',
                'bio' => 'Tech innovator building the future of property management.',
                'photo' => 'uploads/images/cto.png',
                'social' => [
                    'linkedin' => '',
                    'facebook' => '',
                    'instagram' => '',
                    'email' => '',
                ],
            ],
            [
                'name' => 'Nimcel Abellon',
                'position' => 'Head of Operations',
                'bio' => 'Ensures every listing meets our quality standards.',
                'photo' => 'uploads/images/head-operations.png',
                'social' => [
                    'linkedin' => '',
                    'facebook' => '',
                    'instagram' => '',
                    'email' => '',
                ],
            ],
            [
                'name' => 'Edith Balansag',
                'position' => 'Head of Customer Success',
                'bio' => 'Dedicated to helping tenants and landlords succeed.',
                'photo' => 'uploads/images/head-customer-success.png',
                'social' => [
                    'linkedin' => '',
                    'facebook' => '',
                    'instagram' => '',
                    'email' => '',
                ],
            ],
        ];

        $highlightSlides = [];
        foreach ($productRepository->findApprovedRecent(8) as $product) {
            $highlightSlides[] = [
                'source' => 'listing',
                'id' => $product->getId(),
                'title' => $product->getName(),
                'line1' => $product->getCategory()?->getName() ?? 'Verified on CasaClick',
                'line2' => '₱'.number_format((float) $product->getPrice(), 0, '.', ',').' / month',
                'image' => $product->getImage() ? 'uploads/images/'.$product->getImage() : null,
                'fallbackImage' => null,
            ];
        }

        if ($highlightSlides === []) {
            $highlightSlides = [
                [
                    'source' => 'sample',
                    'id' => null,
                    'title' => 'Sunlit loft living',
                    'line1' => 'Open plan · City views',
                    'line2' => 'Browse verified listings on CasaClick',
                    'image' => null,
                    'fallbackImage' => 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=1600&q=75',
                ],
                [
                    'source' => 'sample',
                    'id' => null,
                    'title' => 'Calm waterfront residence',
                    'line1' => 'Two bedrooms · Balcony',
                    'line2' => 'Find your next home in minutes',
                    'image' => null,
                    'fallbackImage' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1600&q=75',
                ],
                [
                    'source' => 'sample',
                    'id' => null,
                    'title' => 'Modern kitchen & dining',
                    'line1' => 'Premium finishes',
                    'line2' => 'See real photos on every listing',
                    'image' => null,
                    'fallbackImage' => 'https://images.unsplash.com/photo-1600210492486-724fe5c67fb0?auto=format&fit=crop&w=1600&q=75',
                ],
                [
                    'source' => 'sample',
                    'id' => null,
                    'title' => 'Resort-style amenities',
                    'line1' => 'Pool · Gym · Lounge',
                    'line2' => 'Discover spaces that match your lifestyle',
                    'image' => null,
                    'fallbackImage' => 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?auto=format&fit=crop&w=1600&q=75',
                ],
            ];
        }

        return $this->render('about/index.html.twig', [
            'team' => $team,
            'highlightSlides' => $highlightSlides,
        ]);
    }
}
