<?php

namespace App\Security;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class JWTAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {
        /** @var User $user */
        $user = $token->getUser();

        if (!$user instanceof User) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid user',
            ], 401);
        }

        if (!$user->isEmailVerified()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Please verify your email address before logging in',
                'verified' => false,
            ], 403);
        }

        $jwt = $this->jwtManager->create($user);

        return new JsonResponse([
            'token' => $jwt,
            'user' => [
                'username' => $user->getUserIdentifier(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'verified' => $user->isEmailVerified(),
            ],
        ]);
    }
}
