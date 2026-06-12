<?php

declare(strict_types=1);

namespace App\Inscripcion\Infrastructure\Persistence;

use App\Inscripcion\Domain\Entity\Documento;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DocumentoRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Documento $documento): void
    {
        $this->entityManager->persist($documento);
        $this->entityManager->flush();
    }

    public function remove(Documento $documento): void
    {
        $this->entityManager->remove($documento);
        $this->entityManager->flush();
    }

    public function findById(int $id): ?Documento
    {
        return $this->entityManager->find(Documento::class, $id);
    }

    /** @return list<Documento> */
    public function findByInscripcion(int $inscripcionId): array
    {
        return $this->entityManager
            ->getRepository(Documento::class)
            ->findBy(['inscripcion' => $inscripcionId], ['createdAt' => 'ASC']);
    }
}
