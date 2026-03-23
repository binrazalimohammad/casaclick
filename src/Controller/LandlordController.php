<?php

namespace App\Controller;

use App\Entity\Landlord;
use App\Form\LandlordType;
use App\Repository\LandlordRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Routing\Attribute\Route;

// Load form configuration for performance optimization
require_once __DIR__ . '/../../config/landlord_form_config.php';

#[Route('landlord')]
final class LandlordController extends AbstractController
{
    #[Route(name: 'app_landlord_index', methods: ['GET'])]
    public function index(LandlordRepository $landlordRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_TENANT');
        
        return $this->render('landlord/index.html.twig', [
            'landlords' => $landlordRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_landlord_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_LANDLORD');
        
        $landlord = new Landlord();
        $form = $this->createForm(LandlordType::class, $landlord);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Handle photo upload
                $photoFile = $form->get('photo')->getData();
                if ($photoFile) {
                    $newFilename = uniqid() . '.' . $photoFile->guessExtension();
                    try {
                        $photoFile->move(
                            $this->getParameter('images_directory'),
                            $newFilename
                        );
                        $landlord->setPhoto($newFilename);
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Failed to upload photo.');
                    }
                }

                $entityManager->persist($landlord);
                $entityManager->flush();

                $this->addFlash('success', 'Landlord created successfully!');
                return $this->redirectToRoute('app_landlord_index', [], Response::HTTP_SEE_OTHER);
            } catch (\Exception $e) {
                $this->addFlash('error', 'An error occurred while creating the landlord. Please try again.');
            }
        }

        return $this->render('landlord/new.html.twig', [
            'landlord' => $landlord,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_landlord_show', methods: ['GET'])]
    public function show(Landlord $landlord): Response
    {
        $this->denyAccessUnlessGranted('ROLE_TENANT');
        
        return $this->render('landlord/show.html.twig', [
            'landlord' => $landlord,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_landlord_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Landlord $landlord, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_LANDLORD');
        
        $form = $this->createForm(LandlordType::class, $landlord);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Handle photo upload
                $photoFile = $form->get('photo')->getData();
                if ($photoFile) {
                    $newFilename = uniqid() . '.' . $photoFile->guessExtension();
                    try {
                        $photoFile->move(
                            $this->getParameter('images_directory'),
                            $newFilename
                        );
                        $landlord->setPhoto($newFilename);
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Failed to upload photo.');
                    }
                }

                $entityManager->flush();

                $this->addFlash('success', 'Landlord updated successfully!');
                return $this->redirectToRoute('app_landlord_index', [], Response::HTTP_SEE_OTHER);
            } catch (\Exception $e) {
                $this->addFlash('error', 'An error occurred while updating the landlord. Please try again.');
            }
        }

        return $this->render('landlord/edit.html.twig', [
            'landlord' => $landlord,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_landlord_delete', methods: ['POST'])]
    public function delete(Request $request, Landlord $landlord, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_LANDLORD');
        
        if ($this->isCsrfTokenValid('delete'.$landlord->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($landlord);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_landlord_index', [], Response::HTTP_SEE_OTHER);
    }
}
