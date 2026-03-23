<?php

namespace App\Repository;

use App\Entity\Application;
use App\Entity\User;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Application>
 */
class ApplicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Application::class);
    }

    /**
     * @return Application[]
     */
    public function findByLandlord(User $landlord): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.landlord = :landlord')
            ->setParameter('landlord', $landlord)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Application[]
     */
    public function findByTenant(User $tenant): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.tenant = :tenant')
            ->setParameter('tenant', $tenant)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByListing(Product $listing): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.listing = :listing')
            ->setParameter('listing', $listing)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingByLandlord(User $landlord): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.landlord = :landlord')
            ->andWhere('a.status = :status')
            ->setParameter('landlord', $landlord)
            ->setParameter('status', 'pending')
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if a listing has an approved application (is occupied)
     */
    public function isListingOccupied(Product $listing): bool
    {
        $result = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.listing = :listing')
            ->andWhere('a.status = :status')
            ->setParameter('listing', $listing)
            ->setParameter('status', 'approved')
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }

    /**
     * Get the approved application for a listing (if any)
     */
    public function findApprovedByListing(Product $listing): ?Application
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.listing = :listing')
            ->andWhere('a.status = :status')
            ->setParameter('listing', $listing)
            ->setParameter('status', 'approved')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

