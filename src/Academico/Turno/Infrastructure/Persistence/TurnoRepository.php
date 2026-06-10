<?php

declare(strict_types=1);

namespace App\Academico\Turno\Infrastructure\Persistence;

use App\Academico\Turno\Domain\Entity\Turno;
use Doctrine\ORM\EntityManagerInterface;

final readonly class TurnoRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Turno $turno): void
    {
        $this->entityManager->persist($turno);
        $this->entityManager->flush();
    }

    public function findById(int $id): ?Turno
    {
        return $this->entityManager->find(Turno::class, $id);
    }

    public function findByNombre(string $nombre): ?Turno
    {
        return $this->entityManager->getRepository(Turno::class)->findOneBy(['nombre' => mb_strtoupper(trim($nombre))]);
    }

    /** @return list<Turno> */
    public function listAll(): array
    {
        return $this->entityManager->getRepository(Turno::class)->findBy([], ['horaInicio' => 'ASC']);
    }

    /** @return list<Turno> */
    public function listActive(): array
    {
        return $this->entityManager->getRepository(Turno::class)->findBy(['estado' => Turno::ESTADO_ACTIVO], ['horaInicio' => 'ASC']);
    }
}
