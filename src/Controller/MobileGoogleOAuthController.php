<?php

namespace App\Controller;

use App\Service\MobileGoogleOAuthBridge;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Starts browser Google OAuth for the React Native app.
 * Callback uses existing /connect/google/check (same redirect URI as staff web login).
 */
#[Route('/mobile/google')]
class MobileGoogleOAuthController extends AbstractController
{
    #[Route('/start', name: 'mobile_google_start', methods: ['GET'])]
    public function start(Request $request, MobileGoogleOAuthBridge $bridge): RedirectResponse
    {
        $role = (string) $request->query->get('role', 'ROLE_TENANT');
        $bridge->setMobileRole($request, $role);

        return $this->redirectToRoute('app_google_connect');
    }
}
