<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use App\Repository\UserRepository;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/mobile', name: 'api_mobile_')]
class MobileApiController extends AbstractController
{
    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json([
                'success' => false,
                'error' => 'Unauthorized',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'success' => true,
            'data' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'roles' => $user->getRoles(),
                'emailVerified' => $user->isEmailVerified(),
            ],
        ]);
    }

    #[Route('/listings', name: 'listings', methods: ['GET'])]
    public function listings(ProductRepository $productRepository): JsonResponse
    {
        $products = $productRepository->findApprovedWithLandlord();

        $data = array_map(fn ($p) => [
            'id' => $p->getId(),
            'name' => $p->getName(),
            'price' => $p->getPrice(),
            'description' => $p->getDescription(),
            'image' => $p->getImage() ? '/uploads/images/' . $p->getImage() : null,
            'category' => $p->getCategory()?->getName(),
            'createdAt' => $p->getCreatedAt()?->format('c'),
        ], $products);

        return $this->json([
            'success' => true,
            'data' => $data,
            'meta' => ['count' => count($data)],
        ], Response::HTTP_OK, [], ['json_encode_options' => JSON_UNESCAPED_SLASHES]);
    }

    #[Route('/listings/{id}', name: 'listing_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function listingShow(int $id, ProductRepository $productRepository): JsonResponse
    {
        $product = $productRepository->findApprovedById($id);

        if (!$product) {
            return $this->json(['success' => false, 'error' => 'Listing not found'], Response::HTTP_NOT_FOUND);
        }

        $data = [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'price' => $product->getPrice(),
            'description' => $product->getDescription(),
            'image' => $product->getImage() ? '/uploads/images/' . $product->getImage() : null,
            'category' => $product->getCategory()?->getName(),
            'landlord' => $product->getCreatedBy() ? [
                'name' => $product->getCreatedBy()->getName(),
            ] : null,
            'createdAt' => $product->getCreatedAt()?->format('c'),
        ];

        return $this->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    #[Route('/categories', name: 'categories', methods: ['GET'])]
    public function categories(CategoryRepository $categoryRepository): JsonResponse
    {
        $categories = $categoryRepository->findBy([], ['name' => 'ASC']);

        $data = array_map(fn ($c) => [
            'id' => $c->getId(),
            'name' => $c->getName(),
        ], $categories);

        return $this->json([
            'success' => true,
            'data' => $data,
            'meta' => ['count' => count($data)],
        ]);
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        EmailVerificationService $verificationService,
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true) ?? [];

        $user = new User();
        $user->setName($payload['name'] ?? '');
        $user->setEmail($payload['email'] ?? '');
        $plainPassword = $payload['password'] ?? '';
        $role = in_array($payload['role'] ?? '', ['ROLE_LANDLORD', 'ROLE_TENANT']) ? $payload['role'] : 'ROLE_TENANT';

        $errors = [];
        if (strlen($user->getName()) < 2) {
            $errors[] = 'Name must be at least 2 characters';
        }
        if (!filter_var($user->getEmail(), FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email';
        }
        if (strlen($plainPassword) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }

        if (!empty($errors)) {
            return $this->json([
                'success' => false,
                'errors' => $errors,
            ], Response::HTTP_BAD_REQUEST);
        }

        $existing = $em->getRepository(User::class)->findOneBy(['email' => $user->getEmail()]);
        if ($existing) {
            return $this->json([
                'success' => false,
                'error' => 'Email already registered',
            ], Response::HTTP_CONFLICT);
        }

        $user->setRoles([$role]);
        $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
        $em->persist($user);
        $em->flush();

        try {
            $verificationService->sendVerificationEmail($user);
            $em->flush();
        } catch (\Throwable $e) {
            // Continue - user is created
        }

        return $this->json([
            'success' => true,
            'data' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'role' => $role,
                'emailVerified' => $user->isEmailVerified(),
                'message' => 'Registration successful. Please verify your email.',
            ],
        ], Response::HTTP_CREATED);
    }

    #[Route('/verify-email/{token}', name: 'verify_email', methods: ['GET'])]
    public function verifyEmail(
        string $token,
        UserRepository $userRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $user = $userRepository->findOneBy(['verificationToken' => $token]);

        if (!$user) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid or expired verification link',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user->setEmailVerified(true);
        $user->setVerificationToken(null);
        $em->flush();

        return $this->json([
            'success' => true,
            'data' => [
                'email' => $user->getEmail(),
                'emailVerified' => true,
                'message' => 'Email verified successfully.',
            ],
        ]);
    }
}
