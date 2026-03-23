<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function countAll(): int
    {
        return (int) $this->count([]);
    }

    public function countByRole(string $role): int
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = "SELECT COUNT(*) as count FROM user WHERE JSON_CONTAINS(roles, :role) = 1";
        $result = $conn->executeQuery($sql, ['role' => json_encode($role)]);
        $row = $result->fetchAssociative();
        return (int) ($row['count'] ?? 0);
    }
}
