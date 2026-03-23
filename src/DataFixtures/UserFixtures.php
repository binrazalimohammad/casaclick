<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
       
        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setName('Admin');
        $admin->setRoles(['ROLE_ADMIN']);
        $hashedPassword = $this->passwordHasher->hashPassword($admin, 'admin1234');
        $admin->setPassword($hashedPassword);
        $manager->persist($admin);

        
        $landlord = new User();
        $landlord->setEmail('landlord@example.com');
        $landlord->setName('Landlord');
        $landlord->setRoles(['ROLE_LANDLORD']);
        $hashedPassword = $this->passwordHasher->hashPassword($landlord, 'landlord3333');
        $landlord->setPassword($hashedPassword);
        $landlord->setIsEnabled(true);
        $manager->persist($landlord);

      
        $tenant = new User();
        $tenant->setEmail('tenant@example.com');
        $tenant->setName('Tenant');
        $tenant->setRoles(['ROLE_TENANT']);
        $hashedPassword = $this->passwordHasher->hashPassword($tenant, 'tenant2222');
        $tenant->setPassword($hashedPassword);
        $tenant->setIsEnabled(true);
        $manager->persist($tenant);

        $manager->flush();
    }
}


