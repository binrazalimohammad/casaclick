<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        EmailVerificationService $verificationService,
    ): Response {
        if ($this->getUser()) {
            if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_STAFF')) {
                return $this->redirectToRoute('app_dashboard');
            }
            return $this->redirectToRoute('app_product_index');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Get selected role from form - ensure only one role is assigned
            $selectedRole = $form->get('role')->getData();
            
            // Ensure only one role is set (form has multiple => false, but double-check)
            if (is_array($selectedRole)) {
                $selectedRole = !empty($selectedRole) ? $selectedRole[0] : 'ROLE_TENANT';
            }
            
            // Validate role is one of the allowed roles
            $allowedRoles = ['ROLE_LANDLORD', 'ROLE_TENANT'];
            if (!in_array($selectedRole, $allowedRoles, true)) {
                $selectedRole = 'ROLE_TENANT'; // Default to tenant if invalid
            }
            
            // Set only one role
            $user->setRoles([$selectedRole]);
            
            $hashedPassword = $passwordHasher->hashPassword(
                $user,
                $form->get('plainPassword')->getData()
            );
            $user->setPassword($hashedPassword);

            $em->persist($user);
            $em->flush();

            try {
                $verificationService->sendVerificationEmail($user);
                $em->flush(); // persist token
                $this->addFlash('success', 'Account created! Please check your email to verify your account before signing in.');
            } catch (\Throwable $e) {
                $this->addFlash('success', 'Account created. Please sign in. (Verification email could not be sent.)');
            }

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}


