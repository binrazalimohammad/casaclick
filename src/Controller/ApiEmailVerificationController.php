<?php

namespace App\Controller;

use App\Entity\User;
use App\Exception\MailNotConfiguredException;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api')]
class ApiEmailVerificationController extends AbstractController
{
    public function __construct(
        private EmailVerificationService $emailVerificationService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Verify email with the token sent in the verification email.
     *
     * Postman / API clients (recommended):
     *   POST /api/verify-email
     *   Content-Type: application/json
     *   Body: { "token": "<verification_token_from_email_or_DB>" }
     *
     * Browser or simple GET (token visible in URL — avoid sharing links):
     *   GET /api/verify-email?token=<verification_token>
     */
    #[Route('/verify-email', name: 'api_verify_email', methods: ['POST', 'GET'])]
    public function verifyEmail(Request $request): JsonResponse
    {
        if ($request->isMethod('GET')) {
            $token = $request->query->get('token');
        } else {
            $data = json_decode($request->getContent(), true) ?? [];
            $token = $data['token'] ?? null;
        }

        if (!$token) {
            return $this->json([
                'success' => false,
                'message' => 'Verification token is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->emailVerificationService->verifyToken((string) $token);

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid or expired verification token',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user->setEmailVerified(true);
        $user->setVerificationToken(null);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Email verified successfully',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'isVerified' => $user->isEmailVerified(),
            ],
        ], Response::HTTP_OK);
    }

    #[Route('/resend-verification', name: 'api_resend_verification', methods: ['POST'])]
    public function resendVerification(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isEmailVerified()) {
            return $this->json([
                'success' => false,
                'message' => 'Email is already verified',
            ], Response::HTTP_BAD_REQUEST);
        }

        $verificationToken = $this->emailVerificationService->generateVerificationToken();
        $user->setVerificationToken($verificationToken);
        $this->entityManager->flush();

        $verificationUrl = $this->emailVerificationService->generateVerifyEmailUrl($verificationToken);

        try {
            $this->emailVerificationService->sendVerificationEmail($user, $verificationUrl);
        } catch (MailNotConfiguredException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => 'Could not send verification email.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->json([
            'success' => true,
            'message' => 'Verification email sent successfully',
        ], Response::HTTP_OK);
    }

    #[Route('/verification-status', name: 'api_verification_status', methods: ['GET'])]
    public function verificationStatus(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'success' => true,
            'isVerified' => $user->isEmailVerified(),
            'email' => $user->getEmail(),
        ], Response::HTTP_OK);
    }
}
