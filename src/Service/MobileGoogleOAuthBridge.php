<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Completes Google OAuth started from the React Native app via /mobile/google/start.
 * Uses the same redirect URI as staff web login (/connect/google/check).
 */
class MobileGoogleOAuthBridge
{
    public const SESSION_ROLE = 'mobile_google_role';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly ActivityLogService $activityLogService,
        private readonly MobileUserProvisioningService $mobileUserProvisioning,
    ) {
    }

    public function setMobileRole(Request $request, string $role): void
    {
        if (!in_array($role, ['ROLE_TENANT', 'ROLE_LANDLORD'], true)) {
            $role = 'ROLE_TENANT';
        }
        $request->getSession()->start();
        $request->getSession()->set(self::SESSION_ROLE, $role);
    }

    /** Returns tenant/landlord role if this OAuth flow is for the mobile app; otherwise null. */
    public function consumeMobileRole(Request $request): ?string
    {
        $session = $request->getSession();
        if (!$session->has(self::SESSION_ROLE)) {
            return null;
        }

        $role = (string) $session->get(self::SESSION_ROLE);
        $session->remove(self::SESSION_ROLE);

        if (!in_array($role, ['ROLE_TENANT', 'ROLE_LANDLORD'], true)) {
            return 'ROLE_TENANT';
        }

        return $role;
    }

    public function completeForMobile(
        string $role,
        string $email,
        string $googleId,
        ?string $name,
    ): RedirectResponse {
        $user = $this->em->getRepository(User::class)->findOneBy(['googleId' => $googleId])
            ?? $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        $isNew = false;

        if (!$user) {
            $isNew = true;
            $user = new User();
            $user->setEmail($email);
            $user->setName($name ?? explode('@', $email)[0]);
            $user->setGoogleId($googleId);
            $user->setRoles([$role]);
            $user->setEmailVerified(true);
            $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));
            $this->em->persist($user);
            $this->mobileUserProvisioning->ensureTenantProfile($user);
        } else {
            if (!$user->isEnabled()) {
                return $this->redirectToApp('Your account has been disabled. Please contact support.');
            }

            $user->setGoogleId($googleId);
            if (!$user->getName() && $name) {
                $user->setName($name);
            }
            $user->setEmailVerified(true);
        }

        $this->em->flush();

        if ($isNew) {
            $this->activityLogService->logEvent(
                $user,
                'MOBILE_REGISTER',
                sprintf('Registered via Google browser (%s)', $email),
                $user->getPrimaryRole(),
                'App\\Entity\\User',
                (string) $user->getId(),
            );
        }

        $jwt = $this->jwtManager->create($user);

        return $this->redirectToApp(null, $jwt);
    }

    public function redirectToApp(?string $error, ?string $jwt = null): RedirectResponse
    {
        $params = [];
        if ($jwt !== null && $jwt !== '') {
            $params['token'] = $jwt;
        }
        if ($error !== null && $error !== '') {
            $params['error'] = $error;
        }

        $query = http_build_query($params);

        return new RedirectResponse('com.binrazali://oauth'.($query !== '' ? '?'.$query : ''));
    }
}
