<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class GoogleConnectController extends AbstractController
{
    #[Route('/connect/google', name: 'app_google_connect', methods: ['GET'])]
    public function connect(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry
            ->getClient('google')
            ->redirect(['email', 'profile'], []);
    }

    #[Route('/connect/google/check', name: 'app_google_connect_check', methods: ['GET'])]
    public function check(
        Request $request,
        ClientRegistry $clientRegistry,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        Security $security,
    ): Response {
        $client = $clientRegistry->getClient('google');
        $token = $client->fetchAccessToken();

        if (isset($token['error'])) {
            $this->addFlash('error', 'Google login failed. Please try again.');
            return $this->redirectToRoute('app_login');
        }

        $googleUser = $client->fetchUserFromToken($token);
        $email = $googleUser->getEmail();
        $googleId = (string) $googleUser->getId();
        $name = $googleUser->getName() ?? $email;

        $user = $em->getRepository(User::class)->findOneBy(['googleId' => $googleId])
            ?? $em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            // Create new Staff user (Google login is for Staff)
            $user = new User();
            $user->setEmail($email);
            $user->setName($name);
            $user->setGoogleId($googleId);
            $user->setRoles(['ROLE_STAFF']);
            $user->setEmailVerified(true); // Google-verified
            $user->setPassword($passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));
            $em->persist($user);
        } else {
            $user->setGoogleId($googleId);
            $user->setEmailVerified(true); // Auto-verify on Google login
        }

        $em->flush();

        $security->login($user, 'form_login');

        return $this->redirectToRoute('app_dashboard');
    }
}
