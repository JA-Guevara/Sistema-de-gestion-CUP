<?php

declare(strict_types=1);

namespace App\Usuario\Infrastructure\Persistence;

use App\Usuario\Domain\Catalog\PermissionCatalog;
use App\Usuario\Domain\Entity\Permission;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PermissionRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Permission $permission): void
    {
        $this->entityManager->persist($permission);
        $this->entityManager->flush();
    }

    public function findByCode(string $code): ?Permission
    {
        return $this->entityManager
            ->getRepository(Permission::class)
            ->findOneBy(['code' => trim($code)]);
    }

    /** @return list<Permission> */
    public function findByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        return $this->entityManager
            ->getRepository(Permission::class)
            ->createQueryBuilder('p')
            ->where('p.id IN (:ids)')
            ->andWhere('p.active = true')
            ->setParameter('ids', $ids)
            ->orderBy('p.module', 'ASC')
            ->addOrderBy('p.action', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Permission> */
    public function listActive(): array
    {
        return $this->entityManager
            ->getRepository(Permission::class)
            ->createQueryBuilder('p')
            ->where('p.active = true')
            ->andWhere('p.code IN (:assignableCodes)')
            ->setParameter('assignableCodes', PermissionCatalog::assignableCodes())
            ->orderBy('p.module', 'ASC')
            ->addOrderBy('p.action', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return array<string,list<Permission>> */
    public function listGroupedByModule(): array
    {
        $grouped = [];

        foreach ($this->listActive() as $permission) {
            $grouped[$permission->module][] = $permission;
        }

        return $grouped;
    }
}
