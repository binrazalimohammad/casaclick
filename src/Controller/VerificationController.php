<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class VerificationController extends AbstractController
{
    /**
     * Uses query-string redirects instead of flash messages so the browser does not need a session
     * for this request (avoids slow/failed session writes when opening the link from email clients).
     */
    #[Route('/verify-email/{token}', name: 'app_verify_email', methods: ['GET'])]
    public function verify(
        string $token,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        LoggerInterface $logger,
    ): Response {
        $token = trim($token);
        if ($token === '') {
            return $this->redirectToRoute('app_login', ['verification' => 'invalid']);
        }

        try {
            $user = $userRepository->findOneBy(['verificationToken' => $token]);

            if (!$user) {
                return $this->redirectToRoute('app_login', ['verification' => 'invalid']);
            }

            $user->setEmailVerified(true);
            $user->setVerificationToken(null);
            $em->flush();
        } catch (\Throwable $e) {
            $logger->error('Email verification failed.', [
                'exception' => $e,
            ]);

            return $this->redirectToRoute('app_login', ['verification' => 'error']);
        }

        return $this->redirectToRoute('app_login', ['verification' => 'success']);
    }
}
