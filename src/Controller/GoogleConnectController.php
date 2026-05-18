<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Psr\Log\LoggerInterface;
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
    public function connect(Request $request, ClientRegistry $clientRegistry): RedirectResponse
    {
        // Ensure a session cookie is issued before leaving the site for Google (helps some browsers).
        $request->getSession()->start();

        // Empty scopes: league/oauth2-google already sends openid, email, profile (openid first).
        return $clientRegistry
            ->getClient('google')
            ->redirect([], []);
    }

    #[Route('/connect/google/check', name: 'app_google_connect_check', methods: ['GET'])]
    public function check(
        Request $request,
        ClientRegistry $clientRegistry,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        Security $security,
        LoggerInterface $logger,
    ): Response {
        $client = $clientRegistry->getClient('google');
        try {
            // Compatible with multiple knpu/oauth2-client-bundle versions
            $token = method_exists($client, 'fetchAccessToken')
                ? $client->fetchAccessToken()
                : $client->getAccessToken();
        } catch (\Throwable $e) {
            $context = [
                'exception' => $e,
                'route' => $request->attributes->get('_route'),
                'uri' => $request->getUri(),
                'query' => $request->query->all(),
            ];
            if ($e instanceof IdentityProviderException) {
                $context['google_response'] = $e->getResponseBody();
            }
            $logger->error('Google OAuth callback failed while fetching access token.', $context);

            $message = 'Google login failed. Please try again.';
            if ($this->getParameter('kernel.debug')) {
                $detail = $e->getMessage();
                if ($e instanceof IdentityProviderException && null !== $e->getResponseBody()) {
                    $detail .= ' — '.json_encode($e->getResponseBody());
                }
                $message .= ' ('.$detail.')';
            }
            $this->addFlash('error', $message);
            return $this->redirectToRoute('app_login');
        }

        try {
            $googleUser = $client->fetchUserFromToken($token);
            $email = $googleUser->getEmail();
            $googleId = (string) $googleUser->getId();
            $name = $googleUser->getName() ?? $email;
        } catch (\Throwable $e) {
            $logger->error('Google OAuth callback failed while fetching user profile.', [
                'exception' => $e,
                'route' => $request->attributes->get('_route'),
                'uri' => $request->getUri(),
            ]);
            $this->addFlash('error', 'Google login failed while fetching your profile. Please try again.');
            return $this->redirectToRoute('app_login');
        }

        if (!$email) {
            $logger->warning('Google OAuth returned no email address.', [
                'google_id' => $googleId ?? null,
                'name' => $name ?? null,
            ]);
            $this->addFlash('error', 'Google did not share an email address. Please choose a Google account with an email.');
            return $this->redirectToRoute('app_login');
        }

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

        $userId = $user->getId();
        $user = $em->getRepository(User::class)->find($userId);
        if (!$user) {
            $logger->error('Google OAuth: user missing after flush.', ['expected_id' => $userId]);
            $this->addFlash('error', 'Could not complete sign-in. Please try again.');
            return $this->redirectToRoute('app_login');
        }

        // Must return the response from Security::login() when non-null so the session
        // and security listeners (success handler, remember-me, etc.) apply to the same response.
        try {
            $loginResponse = $security->login($user, 'form_login', 'main');
        } catch (\Throwable $e) {
            $logger->error('Google OAuth: Security::login failed.', ['exception' => $e]);
            $this->addFlash('error', 'Could not complete sign-in. Please try again or use email and password.');
            return $this->redirectToRoute('app_login');
        }

        return $loginResponse ?? $this->redirectToRoute('app_dashboard');
    }
}
