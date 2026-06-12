<?php

declare(strict_types=1);

namespace App\Academico\Grupo\Infrastructure\Persistence;

use App\Academico\Grupo\Domain\Entity\Grupo;
use App\Gestion\Domain\Entity\Gestion;
use Doctrine\ORM\EntityManagerInterface;

final readonly class GrupoRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Grupo $grupo): void
    {
        $this->entityManager->persist($grupo);
        $this->entityManager->flush();
    }

    public function saveMany(array $grupos): void
    {
        foreach ($grupos as $grupo) {
            $this->entityManager->persist($grupo);
        }
        $this->entityManager->flush();
    }

    public function findById(int $id): ?Grupo
    {
        return $this->entityManager->find(Grupo::class, $id);
    }

    /**
     * @param list<int> $ids
     * @return list<Grupo>
     */
    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->entityManager->getRepository(Grupo::class)->findBy(['id' => array_values(array_unique($ids))]);
    }

    public function findByGestionAndCodigo(Gestion $gestion, string $codigo): ?Grupo
    {
        return $this->entityManager->getRepository(Grupo::class)->findOneBy(['gestion' => $gestion, 'codigo' => mb_strtoupper(trim($codigo))]);
    }

    /** @return list<Grupo> */
    public function listAll(): array
    {
        return $this->entityManager->getRepository(Grupo::class)->findBy([], ['createdAt' => 'DESC']);
    }

    public function countByGestion(int $gestionId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(g.id)')
            ->from(Grupo::class, 'g')
            ->where('g.gestion = :gestionId')
            ->setParameter('gestionId', $gestionId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
