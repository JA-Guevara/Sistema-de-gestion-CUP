<?php

declare(strict_types=1);

namespace App\Gestion\Infrastructure\Persistence;

use App\Gestion\Domain\Catalog\EstadoGestion;
use App\Gestion\Domain\Entity\Gestion;
use Doctrine\ORM\EntityManagerInterface;

final readonly class GestionRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Gestion $gestion): void
    {
        $this->entityManager->persist($gestion);
        $this->entityManager->flush();
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }

    public function findById(int $id): ?Gestion
    {
        return $this->entityManager->find(Gestion::class, $id);
    }

    public function findByCodigo(string $codigo): ?Gestion
    {
        return $this->entityManager
            ->getRepository(Gestion::class)
            ->findOneBy(['codigo' => mb_strtoupper(trim($codigo))]);
    }

    public function findActive(): ?Gestion
    {
        return $this->entityManager->createQueryBuilder()
            ->select('g')
            ->from(Gestion::class, 'g')
            ->where('g.fechaActivacion IS NOT NULL')
            ->andWhere('g.fechaFinalizacion IS NULL')
            ->andWhere('g.estado NOT IN (:closedStates)')
            ->setParameter('closedStates', [EstadoGestion::FINALIZADA, EstadoGestion::CANCELADA])
            ->orderBy('g.fechaActivacion', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<Gestion> */
    public function listAll(): array
    {
        return $this->entityManager
            ->getRepository(Gestion::class)
            ->findBy([], ['createdAt' => 'DESC']);
    }

    public function closeCurrentGestionesExcept(?Gestion $selected): void
    {
        foreach ($this->listAll() as $gestion) {
            if ($selected !== null && $gestion->id === $selected->id) {
                continue;
            }

            if ($gestion->isActive()) {
                $gestion->replaceAsInactive();
            }
        }
    }
}
