<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class VerificationController extends AbstractController
{
    #[Route('/verify-email/{token}', name: 'app_verify_email', methods: ['GET'])]
    public function verify(string $token, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        $user = $userRepository->findOneBy(['verificationToken' => $token]);

        if (!$user) {
            $this->addFlash('error', 'Invalid or expired verification link.');
            return $this->redirectToRoute('app_login');
        }

        $user->setEmailVerified(true);
        $user->setVerificationToken(null);
        $em->flush();

        $this->addFlash('success', 'Your email has been verified. You can now sign in.');

        return $this->redirectToRoute('app_login');
    }
}
