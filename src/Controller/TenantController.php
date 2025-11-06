<?php

namespace App\Controller;

use App\Entity\Tenant;
use App\Form\TenantType;
use App\Repository\TenantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TenantController extends AbstractController
{
    #[Route('tenant', name: 'app_tenant', methods: ['GET'])]
    public function index(TenantRepository $tenantRepository): Response
    {
        return $this->render('tenant/index.html.twig', [
            'tenants' => $tenantRepository->findAll(),
        ]);
    }

    #[Route('tenant/new', name: 'app_tenant_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $tenant = new Tenant();
        $form = $this->createForm(TenantType::class, $tenant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($tenant);
            $entityManager->flush();

            return $this->redirectToRoute('app_tenant');
        }

        return $this->render('tenant/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('tenant/{id}', name: 'app_tenant_show', methods: ['GET'])]
    public function show(Tenant $tenant): Response
    {
        return $this->render('tenant/show.html.twig', [
            'tenant' => $tenant,
        ]);
    }

    #[Route('tenant/{id}/edit', name: 'app_tenant_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Tenant $tenant, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TenantType::class, $tenant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
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
        if ($this->isCsrfTokenValid('delete' . $tenant->getId(), $request->request->get('_token'))) {
            $entityManager->remove($tenant);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_tenant');
    }
}
