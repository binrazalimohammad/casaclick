<?php

namespace App\Controller;

use App\Entity\Application;
use App\Entity\Product;
use App\Repository\ApplicationRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('application')]
final class ApplicationController extends AbstractController
{
    public function __construct(
        private NotificationService $notificationService,
    ) {
    }

    #[Route('/listing/{id}/apply', name: 'app_application_new', methods: ['POST'])]
    public function apply(Request $request, Product $listing, EntityManagerInterface $entityManager, ApplicationRepository $applicationRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_TENANT');

        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($applicationRepository->isListingOccupied($listing)) {
            $this->addFlash('error', 'This listing is already occupied. You cannot apply for it.');
            return $this->redirectToRoute('app_product_show', ['id' => $listing->getId()]);
        }

        if ($listing->getCreatedBy() && $listing->getCreatedBy()->getId() === $user->getId()) {
            $this->addFlash('error', 'You cannot apply to your own listing.');
            return $this->redirectToRoute('app_product_show', ['id' => $listing->getId()]);
        }

        $existingApplication = $entityManager->getRepository(Application::class)
            ->createQueryBuilder('a')
            ->where('a.listing = :listing')
            ->andWhere('a.tenant = :tenant')
            ->setParameter('listing', $listing)
            ->setParameter('tenant', $user)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existingApplication) {
            $this->addFlash('error', 'You have already applied for this listing.');
            return $this->redirectToRoute('app_product_show', ['id' => $listing->getId()]);
        }

        $application = new Application();
        $application->setListing($listing);
        $application->setTenant($user);
        $application->setLandlord($listing->getCreatedBy());
        $application->setStatus('pending');
        $application->setMessage($request->request->get('message', ''));

        $entityManager->persist($application);
        $entityManager->flush();

        if ($listing->getCreatedBy()) {
            $this->notificationService->notifyUser(
                $listing->getCreatedBy(),
                'application_submitted',
                sprintf('Tenant %s has applied for your listing: %s', $user->getName(), $listing->getName()),
                'Application',
                $application->getId(),
            );
        }

        $this->addFlash('success', 'Application submitted successfully!');
        return $this->redirectToRoute('app_product_show', ['id' => $listing->getId()]);
    }

    #[Route('/{id}/approve', name: 'app_application_approve', methods: ['POST'])]
    public function approve(Application $application, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_LANDLORD');

        $user = $this->getUser();
        if (!$user || $application->getLandlord()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You can only approve applications for your own listings.');
        }

        if (!$this->isCsrfTokenValid('approve_application_' . $application->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_application_index');
        }

        if ($application->getStatus() !== 'pending') {
            $this->addFlash('warning', 'This application is no longer pending.');
            return $this->redirectToRoute('app_application_index');
        }

        $oldStatus = $application->getStatus();
        $application->setStatus('approved');
        $application->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->notificationService->notifyOrderStatusChange($application, $oldStatus, 'approved');

        $this->addFlash('success', 'Application approved. The tenant will be notified on their device.');
        return $this->redirectToRoute('app_application_index');
    }

    #[Route('/{id}/reject', name: 'app_application_reject', methods: ['POST'])]
    public function reject(Application $application, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_LANDLORD');

        $user = $this->getUser();
        if (!$user || $application->getLandlord()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You can only reject applications for your own listings.');
        }

        if (!$this->isCsrfTokenValid('reject_application_' . $application->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_application_index');
        }

        if ($application->getStatus() !== 'pending') {
            $this->addFlash('warning', 'This application is no longer pending.');
            return $this->redirectToRoute('app_application_index');
        }

        $oldStatus = $application->getStatus();
        $application->setStatus('rejected');
        $application->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->notificationService->notifyOrderStatusChange($application, $oldStatus, 'rejected');

        $this->addFlash('success', 'Application rejected. The tenant will be notified on their device.');
        return $this->redirectToRoute('app_application_index');
    }

    #[Route(name: 'app_application_index', methods: ['GET'])]
    public function index(ApplicationRepository $applicationRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_TENANT');

        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->isGranted('ROLE_LANDLORD')) {
            $applications = $applicationRepository->findByLandlord($user);
        } else {
            $applications = $applicationRepository->findByTenant($user);
        }

        return $this->render('application/index.html.twig', [
            'applications' => $applications,
        ]);
    }
}
