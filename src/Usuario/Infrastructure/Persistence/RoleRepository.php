<?php

declare(strict_types=1);

namespace App\Usuario\Infrastructure\Persistence;

use App\Usuario\Domain\Entity\Role;
use Doctrine\ORM\EntityManagerInterface;

final readonly class RoleRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Role $role): void
    {
        $this->entityManager->persist($role);
        $this->entityManager->flush();
    }

    public function findById(int $id): ?Role
    {
        return $this->entityManager
            ->getRepository(Role::class)
            ->createQueryBuilder('r')
            ->leftJoin('r.permissions', 'p')
            ->addSelect('p')
            ->where('r.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByName(string $name): ?Role
    {
        return $this->entityManager
            ->getRepository(Role::class)
            ->findOneBy(['name' => trim($name)]);
    }

    /** @return list<Role> */
    public function findByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        return $this->entityManager
            ->getRepository(Role::class)
            ->createQueryBuilder('r')
            ->where('r.id IN (:ids)')
            ->andWhere('r.active = true')
            ->setParameter('ids', $ids)
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Role> */
    public function listAll(): array
    {
        return $this->entityManager
            ->getRepository(Role::class)
            ->createQueryBuilder('r')
            ->leftJoin('r.permissions', 'p')
            ->addSelect('p')
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Role> */
    public function listActive(): array
    {
        return $this->entityManager
            ->getRepository(Role::class)
            ->createQueryBuilder('r')
            ->where('r.active = true')
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
