<?php

namespace App\Controller;

use App\Entity\Tenant;
use App\Form\TenantType;
use App\Repository\TenantRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Routing\Attribute\Route;

final class TenantController extends AbstractController
{
    #[Route('tenant', name: 'app_tenant', methods: ['GET'])]
    public function index(TenantRepository $tenantRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_LANDLORD');
        
        return $this->render('tenant/index.html.twig', [
            'tenants' => $tenantRepository->findAll(),
        ]);
    }

    #[Route('tenant/new', name: 'app_tenant_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_LANDLORD');
        
        $tenant = new Tenant();
        $form = $this->createForm(TenantType::class, $tenant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle photo upload
            $photoFile = $form->get('photo')->getData();
            if ($photoFile) {
                $newFilename = uniqid() . '.' . $photoFile->guessExtension();
                try {
                    $photoFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                    $tenant->setPhoto($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload photo.');
                }
            }

            $entityManager->persist($tenant);
            $entityManager->flush();

            $this->addFlash('success', 'Tenant created successfully!');
            return $this->redirectToRoute('app_tenant');
        }

        return $this->render('tenant/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('tenant/{id}', name: 'app_tenant_show', methods: ['GET'])]
    public function show(Tenant $tenant): Response
    {
        $this->denyAccessUnlessGranted('ROLE_LANDLORD');
        
        return $this->render('tenant/show.html.twig', [
            'tenant' => $tenant,
        ]);
    }

    #[Route('tenant/{id}/edit', name: 'app_tenant_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Tenant $tenant, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_LANDLORD');
        
        $form = $this->createForm(TenantType::class, $tenant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle photo upload
            $photoFile = $form->get('photo')->getData();
            if ($photoFile) {
                $newFilename = uniqid() . '.' . $photoFile->guessExtension();
                try {
                    $photoFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                    $tenant->setPhoto($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload photo.');
                }
            }

            $entityManager->flush();
            $this->addFlash('success', 'Tenant updated successfully!');
            return $this->redirectToRoute('app_tenant');
        }

        return $this->render('tenant/edit.html.twig', [
            'form' => $form,
            'tenant' => $tenant,
        ]);
    }

    #[Route('tenant/{id}', name: 'app_tenant_delete', methods: ['POST'])]
    public function delete(Request $request, Tenant $tenant, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_LANDLORD');
        
        if ($this->isCsrfTokenValid('delete' . $tenant->getId(), $request->request->get('_token'))) {
            $entityManager->remove($tenant);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_tenant');
    }

    #[Route('/tenant/profile', name: 'app_tenant_profile', methods: ['GET'])]
    public function profile(TenantRepository $tenantRepository): Response
    {
        // Only allow tenants (not landlords or admins) to access this route
        $user = $this->getUser();
        if (!$user || !in_array('ROLE_TENANT', $user->getRoles()) || $user->getPrimaryRole() !== 'ROLE_TENANT') {
            throw $this->createAccessDeniedException('Only tenants can view their own profile.');
        }
        
        // Find tenant by current user's email - ensures tenants can only access their own profile
        $tenant = $tenantRepository->findOneByEmail($user->getEmail());
        
        if (!$tenant) {
            $this->addFlash('error', 'Tenant profile not found. Please contact administrator.');
            return $this->redirectToRoute('app_account_show');
        }
        
        return $this->render('tenant/profile.html.twig', [
            'tenant' => $tenant,
        ]);
    }

    #[Route('/tenant/profile/edit', name: 'app_tenant_profile_edit', methods: ['GET', 'POST'])]
    public function profileEdit(Request $request, TenantRepository $tenantRepository, EntityManagerInterface $entityManager, NotificationService $notificationService): Response
    {
        // Only allow tenants (not landlords or admins) to access this route
        $user = $this->getUser();
        if (!$user || !in_array('ROLE_TENANT', $user->getRoles()) || $user->getPrimaryRole() !== 'ROLE_TENANT') {
            throw $this->createAccessDeniedException('Only tenants can edit their own profile.');
        }
        
        // Find tenant by current user's email - ensures tenants can only access their own profile
        $tenant = $tenantRepository->findOneByEmail($user->getEmail());
        
        if (!$tenant) {
            $this->addFlash('error', 'Tenant profile not found. Please contact administrator.');
            return $this->redirectToRoute('app_account_show');
        }

        // Store original values for comparison
        $originalEmail = $tenant->getEmail();
        $originalName = $tenant->getName();
        $originalPhone = $tenant->getPhone();
        $originalAddress = $tenant->getAddress();
        $originalModeOfPayment = $tenant->getModeOfPayment();
        $tenantId = $tenant->getId(); // Store tenant ID to verify ownership after form submission
        
        // Disable email field for tenants - they can't change their email (must match account email)
        $form = $this->createForm(TenantType::class, $tenant, [
            'disable_email' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Security: Always ensure tenant email matches user account email
            // This prevents tenants from changing their email even if they try to manipulate the form
            // The email field is disabled, but we enforce it here as well for security
            $tenant->setEmail($user->getEmail());
            
            // Verify tenant ID still matches (additional security check)
            if ($tenant->getId() !== $tenantId) {
                $this->addFlash('error', 'Security violation detected. You can only edit your own profile.');
                return $this->redirectToRoute('app_account_show');
            }
            
            // Check if any field was actually changed
            $hasChanges = 
                $originalName !== $tenant->getName() ||
                $originalPhone !== $tenant->getPhone() ||
                $originalAddress !== $tenant->getAddress() ||
                $originalModeOfPayment !== $tenant->getModeOfPayment() ||
                $form->get('photo')->getData() !== null;
            
            // Handle photo upload
            $photoFile = $form->get('photo')->getData();
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

            $entityManager->flush();
            
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
            return $this->redirectToRoute('app_tenant_profile');
        }

        return $this->render('tenant/profile_edit.html.twig', [
            'form' => $form,
            'tenant' => $tenant,
        ]);
    }
}
