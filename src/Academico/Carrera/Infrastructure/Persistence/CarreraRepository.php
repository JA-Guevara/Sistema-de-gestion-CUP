<?php

declare(strict_types=1);

namespace App\Academico\Carrera\Infrastructure\Persistence;

use App\Academico\Carrera\Domain\Entity\Carrera;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CarreraRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Carrera $carrera): void
    {
        $this->entityManager->persist($carrera);
        $this->entityManager->flush();
    }

    public function findById(int $id): ?Carrera
    {
        return $this->entityManager->find(Carrera::class, $id);
    }

    public function findByCodigo(string $codigo): ?Carrera
    {
        return $this->entityManager
            ->getRepository(Carrera::class)
            ->findOneBy(['codigo' => mb_strtoupper(trim($codigo))]);
    }

    /** @return list<Carrera> */
    public function listAll(): array
    {
        return $this->entityManager
            ->getRepository(Carrera::class)
            ->findBy([], ['nombre' => 'ASC']);
    }

    /** @return list<Carrera> */
    public function listActive(): array
    {
        return $this->entityManager
            ->getRepository(Carrera::class)
            ->findBy(['estado' => Carrera::ESTADO_ACTIVA], ['nombre' => 'ASC']);
    }
}
