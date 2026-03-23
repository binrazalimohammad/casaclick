<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /**
     * @return ActivityLog[]
     */
    public function findRecent(int $limit = 5): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.user', 'u')
            ->addSelect('u')
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ActivityLog[]
     */
    public function findFiltered(?int $userId, ?string $action, ?\DateTimeInterface $from, ?\DateTimeInterface $to, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.user', 'u')
            ->addSelect('u')
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($userId) {
            $qb->andWhere('u.id = :userId')->setParameter('userId', $userId);
        }

        if ($action) {
            $qb->andWhere('l.action = :action')->setParameter('action', $action);
        }

        if ($from) {
            $qb->andWhere('l.createdAt >= :from')->setParameter('from', $from);
        }

        if ($to) {
            $qb->andWhere('l.createdAt <= :to')->setParameter('to', $to);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get all distinct actions that have been used in the logs
     * @return string[]
     */
    public function findDistinctActions(): array
    {
        $results = $this->createQueryBuilder('l')
            ->select('DISTINCT l.action')
            ->orderBy('l.action', 'ASC')
            ->getQuery()
            ->getResult();

        // Extract action values from the result array
        return array_map(function($row) {
            return $row['action'];
        }, $results);
    }
}
