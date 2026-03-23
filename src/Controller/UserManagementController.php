<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\ActivityLogService;

#[Route('/admin/users')]
class UserManagementController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ActivityLogService $activityLogService,
    ) {
    }

    #[Route('/', name: 'app_admin_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/users/index.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_admin_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = new User();
        $form = $this->createForm(UserType::class, $user, [
            'require_password' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);

            // Ensure only one role is set (form has multiple => false, but ensure single role)
            $selectedRole = $form->get('roles')->getData();
            if (is_array($selectedRole)) {
                $selectedRole = !empty($selectedRole) ? $selectedRole[0] : 'ROLE_TENANT';
            }
            // Validate role is one of the allowed roles
            $allowedRoles = ['ROLE_ADMIN', 'ROLE_LANDLORD', 'ROLE_TENANT'];
            if (!in_array($selectedRole, $allowedRoles, true)) {
                $selectedRole = 'ROLE_TENANT'; // Default to tenant if invalid
            }
            $user->setRoles([$selectedRole]);

            $this->em->persist($user);
            $this->em->flush();

            // Log create action
            $this->activityLogService->logAction($this->getUser(), 'CREATE', $user);

            $this->addFlash('success', 'User created successfully.');

            return $this->redirectToRoute('app_admin_user_index');
        }

        return $this->render('admin/users/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_user_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/users/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(UserType::class, $user, [
            'require_password' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if (!empty($plainPassword)) {
                $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            // Ensure only one role is set
            $selectedRole = $form->get('roles')->getData();
            if (is_array($selectedRole)) {
                $selectedRole = !empty($selectedRole) ? $selectedRole[0] : 'ROLE_TENANT';
            }
            // Validate role is one of the allowed roles
            $allowedRoles = ['ROLE_ADMIN', 'ROLE_LANDLORD', 'ROLE_TENANT'];
            if (!in_array($selectedRole, $allowedRoles, true)) {
                $selectedRole = 'ROLE_TENANT'; // Default to tenant if invalid
            }
            $user->setRoles([$selectedRole]);

            $this->em->flush();

            // Log update action
            $this->activityLogService->logAction($this->getUser(), 'UPDATE', $user);

            $this->addFlash('success', 'User updated successfully.');

            return $this->redirectToRoute('app_admin_user_index');
        }

        return $this->render('admin/users/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->getUser() && $user->getId() === $this->getUser()->getId()) {
            $this->addFlash('error', 'You cannot delete your own account.');
            return $this->redirectToRoute('app_admin_user_index');
        }

        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            $this->em->remove($user);
            $this->em->flush();

            // Log delete action
            $this->activityLogService->logAction($this->getUser(), 'DELETE', $user);
            $this->addFlash('success', 'User deleted.');
        }

        return $this->redirectToRoute('app_admin_user_index');
    }
}


