<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Session-authenticated Socket.IO credentials for Twig pages (no manual page refresh).
 */
#[Route('/sync')]
final class WebRealtimeController extends AbstractController
{
    public function __construct(
        private readonly string $wsBroadcastUrl = '',
    ) {
    }

    #[Route('/realtime-session', name: 'app_web_realtime_session', methods: ['GET'])]
    public function realtimeSession(JWTTokenManagerInterface $jwtManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $url = trim($this->wsBroadcastUrl);
        $invalid = $url === ''
            || str_contains($url, '127.0.0.1')
            || str_contains($url, 'localhost')
            || stripos($url, 'REPLACE') !== false
            || stripos($url, 'YOUR-REALTIME') !== false;

        return $this->json([
            'success' => true,
            'realtimeOrigin' => $invalid ? null : rtrim($url, '/'),
            'token' => $jwtManager->create($user),
        ]);
    }
}
