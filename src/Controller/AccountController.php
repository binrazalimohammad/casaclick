<?php

namespace App\Controller;

use App\Entity\Tenant;
use App\Form\ChangePasswordType;
use App\Form\TenantType;
use App\Repository\TenantRepository;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class AccountController extends AbstractController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/account', name: 'app_account_show', methods: ['GET', 'POST'])]
    public function show(Request $request, TenantRepository $tenantRepository, NotificationService $notificationService): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        $editMode = $request->query->getBoolean('edit', false);
        $tenant = null;
        $tenantForm = null;
        $isTenant = $user && $user->getPrimaryRole() === 'ROLE_TENANT';

        // If user is a tenant, get their tenant profile
        if ($isTenant) {
            $tenant = $tenantRepository->findOneByEmail($user->getEmail());
        }

        // Handle tenant profile editing
        if ($isTenant && $tenant && $editMode && $request->isMethod('POST')) {
            // Store original values for comparison
            $originalName = $tenant->getName();
            $originalPhone = $tenant->getPhone();
            $originalAddress = $tenant->getAddress();

            // Create form without modeOfPayment field
            $tenantForm = $this->createForm(TenantType::class, $tenant, [
                'disable_email' => true,
            ]);
            // Remove modeOfPayment field from form
            $tenantForm->remove('modeOfPayment');
            $tenantForm->handleRequest($request);

            if ($tenantForm->isSubmitted() && $tenantForm->isValid()) {
                // Security: Always ensure tenant email matches user account email
                $tenant->setEmail($user->getEmail());

                // Check if any field was actually changed
                $hasChanges = 
                    $originalName !== $tenant->getName() ||
                    $originalPhone !== $tenant->getPhone() ||
                    $originalAddress !== $tenant->getAddress() ||
                    $tenantForm->get('photo')->getData() !== null;

                // Handle photo upload
                $photoFile = $tenantForm->get('photo')->getData();
                if ($photoFile) {
                    // Delete old photo if it exists
                    if ($tenant->getPhoto()) {
                        $oldPhotoPath = $this->getParameter('images_directory') . '/' . $tenant->getPhoto();
                        if (file_exists($oldPhotoPath)) {
                            @unlink($oldPhotoPath);
                        }
                    }

                    $newFilename = uniqid() . '.' . $photoFile->guessExtension();
                    try {
                        $photoFile->move(
                            $this->getParameter('images_directory'),
                            $newFilename
                        );
                        $tenant->setPhoto($newFilename);
                        $hasChanges = true;
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Failed to upload photo.');
                    }
                }

                // Update user name if tenant name changed
                if ($originalName !== $tenant->getName()) {
                    $user->setName($tenant->getName());
                }

                $this->em->flush();

                // Send notification to admin if changes were made
                if ($hasChanges) {
                    $tenantName = $tenant->getName() ?? $tenant->getEmail();
                    $notificationService->notifyAdmin(
                        'tenant_profile_updated',
                        "Tenant {$tenantName} ({$tenant->getEmail()}) has updated their profile.",
                        'Tenant',
                        $tenant->getId()
                    );
                }

                $this->addFlash('success', 'Your profile has been updated successfully!');
                return $this->redirectToRoute('app_account_show');
            }
        } elseif ($isTenant && $tenant && $editMode) {
            // Create form for editing
            $tenantForm = $this->createForm(TenantType::class, $tenant, [
                'disable_email' => true,
            ]);
            // Remove modeOfPayment field from form
            $tenantForm->remove('modeOfPayment');
        }

        return $this->render('account/show.html.twig', [
            'user' => $user,
            'tenant' => $tenant,
            'tenantForm' => $tenantForm?->createView(),
            'editMode' => $editMode,
        ]);
    }

    #[Route('/account/password', name: 'app_account_password', methods: ['GET', 'POST'])]
    public function changePassword(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $current = $form->get('currentPassword')->getData();
            if (!$this->passwordHasher->isPasswordValid($user, $current)) {
                $form->get('currentPassword')->addError(new FormError('Current password is incorrect.'));
            } else {
                $newPassword = $form->get('newPassword')->getData();
                $hashed = $this->passwordHasher->hashPassword($user, $newPassword);
                $user->setPassword($hashed);
                $this->em->flush();

                $this->addFlash('success', 'Password updated.');

                return $this->redirectToRoute('app_account_show');
            }
        }

        return $this->render('account/password.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}


