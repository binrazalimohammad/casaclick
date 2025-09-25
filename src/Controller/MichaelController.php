<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MichaelController extends AbstractController
{
    #[Route('/michael', name: 'app_michael')]
    public function index(): Response
    {
        return $this->render('michael/index.html.twig', [
            'controller_name' => 'MichaelController',
        ]);
    }
}
