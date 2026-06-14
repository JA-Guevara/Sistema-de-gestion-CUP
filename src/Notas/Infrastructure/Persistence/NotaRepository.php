<?php

declare(strict_types=1);

namespace App\Notas\Infrastructure\Persistence;

use App\Notas\Domain\Entity\Nota;
use Doctrine\ORM\EntityManagerInterface;

final readonly class NotaRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function persist(Nota $nota): void
    {
        $this->entityManager->persist($nota);
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }

    public function findById(int $id): ?Nota
    {
        return $this->entityManager->find(Nota::class, $id);
    }

    public function findOne(int $inscripcionId, int $materiaId, int $numeroExamen): ?Nota
    {
        return $this->entityManager
            ->getRepository(Nota::class)
            ->findOneBy([
                'inscripcion' => $inscripcionId,
                'materia' => $materiaId,
                'numeroExamen' => $numeroExamen,
            ]);
    }

    /** @return list<Nota> */
    public function listByMateriaAndGestion(int $materiaId, int $gestionId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('n')
            ->from(Nota::class, 'n')
            ->join('n.inscripcion', 'i')
            ->where('n.materia = :materiaId')
            ->andWhere('i.gestion = :gestionId')
            ->setParameter('materiaId', $materiaId)
            ->setParameter('gestionId', $gestionId)
            ->getQuery()
            ->getResult();
    }

    /** @return list<Nota> */
    public function listByInscripcion(int $inscripcionId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('n', 'm')
            ->from(Nota::class, 'n')
            ->join('n.materia', 'm')
            ->where('n.inscripcion = :inscripcionId')
            ->setParameter('inscripcionId', $inscripcionId)
            ->orderBy('m.nombre', 'ASC')
            ->addOrderBy('n.numeroExamen', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
