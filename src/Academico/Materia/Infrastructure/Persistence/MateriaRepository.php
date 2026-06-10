<?php

declare(strict_types=1);

namespace App\Academico\Materia\Infrastructure\Persistence;

use App\Academico\Materia\Domain\Entity\Materia;
use Doctrine\ORM\EntityManagerInterface;

final readonly class MateriaRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Materia $materia): void
    {
        $this->entityManager->persist($materia);
        $this->entityManager->flush();
    }

    public function findById(int $id): ?Materia
    {
        return $this->entityManager->find(Materia::class, $id);
    }

    public function findByCodigo(string $codigo): ?Materia
    {
        return $this->entityManager->getRepository(Materia::class)->findOneBy(['codigo' => mb_strtoupper(trim($codigo))]);
    }

    /** @return list<Materia> */
    public function listAll(): array
    {
        return $this->entityManager->getRepository(Materia::class)->findBy([], ['nombre' => 'ASC']);
    }

    /** @return list<Materia> */
    public function listActive(): array
    {
        return $this->entityManager->getRepository(Materia::class)->findBy(['estado' => Materia::ESTADO_ACTIVA], ['nombre' => 'ASC']);
    }
}
