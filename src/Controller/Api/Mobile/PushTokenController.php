<?php

declare(strict_types=1);

namespace App\Controller\Api\Mobile;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Register or clear the customer's FCM device token for background push notifications.
 *
 * POST   /api/mobile/push-token  — body: { "token": "<fcm>", "platform": "android"|"ios" }
 * DELETE /api/mobile/push-token  — clears token on logout
 */
#[Route('/api/mobile')]
class PushTokenController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    #[Route('/push-token', name: 'api_mobile_push_token', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function register(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $payload = json_decode($request->getContent(), true) ?? [];
        $token = trim((string) ($payload['token'] ?? ''));

        if ($token === '') {
            return new JsonResponse(['ok' => false, 'error' => 'token required'], 400);
        }

        $user->setFcmToken($token);
        $this->em->flush();

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/push-token', name: 'api_mobile_push_token_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function unregister(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $user->setFcmToken(null);
        $this->em->flush();

        return new JsonResponse(['ok' => true]);
    }
}
