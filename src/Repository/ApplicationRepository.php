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
    /**
     * @return Application[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.listing', 'l')
            ->addSelect('l')
            ->leftJoin('a.tenant', 't')
            ->addSelect('t')
            ->leftJoin('a.landlord', 'ld')
            ->addSelect('ld')
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

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

    public function findOneByTenantAndId(User $tenant, int $id): ?Application
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.tenant = :tenant')
            ->andWhere('a.id = :id')
            ->setParameter('tenant', $tenant)
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Applications for a tenant filtered by one or more statuses (e.g. orders = approved, completed).
     *
     * @param list<string> $statuses
     *
     * @return Application[]
     */
    public function findByTenantAndStatuses(User $tenant, array $statuses): array
    {
        if ($statuses === []) {
            return [];
        }

        return $this->createQueryBuilder('a')
            ->andWhere('a.tenant = :tenant')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('tenant', $tenant)
            ->setParameter('statuses', $statuses)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Fingerprint for live sync (mobile ↔ website, same DB).
     *
     * @return array{count: int, latestUpdatedAt: ?string}
     */
    public function getSyncMetaForTenant(User $tenant): array
    {
        return $this->buildSyncMeta(
            $this->createQueryBuilder('a')
                ->select('COUNT(a.id) AS cnt', 'MAX(COALESCE(a.updatedAt, a.createdAt)) AS maxUpdated')
                ->andWhere('a.tenant = :tenant')
                ->setParameter('tenant', $tenant)
                ->getQuery()
                ->getOneOrNullResult()
        );
    }

    /**
     * @return array{count: int, latestUpdatedAt: ?string}
     */
    public function getSyncMetaForLandlord(User $landlord): array
    {
        return $this->buildSyncMeta(
            $this->createQueryBuilder('a')
                ->select('COUNT(a.id) AS cnt', 'MAX(COALESCE(a.updatedAt, a.createdAt)) AS maxUpdated')
                ->andWhere('a.landlord = :landlord')
                ->setParameter('landlord', $landlord)
                ->getQuery()
                ->getOneOrNullResult()
        );
    }

    /**
     * All bookings (admin web feed).
     *
     * @return array{count: int, latestUpdatedAt: ?string}
     */
    public function getGlobalSyncMeta(): array
    {
        return $this->buildSyncMeta(
            $this->createQueryBuilder('a')
                ->select('COUNT(a.id) AS cnt', 'MAX(COALESCE(a.updatedAt, a.createdAt)) AS maxUpdated')
                ->getQuery()
                ->getOneOrNullResult()
        );
    }

    /**
     * @param array{cnt?: mixed, maxUpdated?: mixed}|null $row
     *
     * @return array{count: int, latestUpdatedAt: ?string}
     */
    private function buildSyncMeta(?array $row): array
    {
        $max = $row['maxUpdated'] ?? null;

        return [
            'count' => (int) ($row['cnt'] ?? 0),
            'latestUpdatedAt' => $max instanceof \DateTimeInterface
                ? $max->format(\DateTimeInterface::ATOM)
                : ($max !== null ? (string) $max : null),
        ];
    }
}

