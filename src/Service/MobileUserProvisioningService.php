<?php

namespace App\Service;

use App\Entity\Tenant;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Keeps optional Tenant profile rows in sync when mobile users register (email or Google).
 */
class MobileUserProvisioningService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function ensureTenantProfile(User $user): void
    {
        if ($user->getPrimaryRole() !== 'ROLE_TENANT') {
            return;
        }

        $email = $user->getEmail();
        if (!$email) {
            return;
        }

        $existing = $this->em->getRepository(Tenant::class)->findOneBy(['email' => $email]);
        if ($existing) {
            if (!$existing->getName() && $user->getName()) {
                $existing->setName($user->getName());
            }

            return;
        }

        $tenant = new Tenant();
        $tenant->setName($user->getName() ?? explode('@', $email)[0]);
        $tenant->setEmail($email);
        $tenant->setPhone($user->getPhone() ?? 'N/A');
        $this->em->persist($tenant);
    }
}
