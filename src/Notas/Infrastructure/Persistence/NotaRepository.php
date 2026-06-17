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

    /**
     * Valores planos de todas las notas de los estudiantes de una gestion, para
     * calcular promedios en lote (Admision Final) sin cargar entidades.
     *
     * @return list<array{insId:int, materiaId:int, numeroExamen:int, valor:int}>
     */
    public function listValoresByGestion(int $gestionId): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(n.inscripcion) AS insId, IDENTITY(n.materia) AS materiaId, n.numeroExamen AS numeroExamen, n.valor AS valor')
            ->from(Nota::class, 'n')
            ->join('n.inscripcion', 'i')
            ->where('i.gestion = :gestionId')
            ->setParameter('gestionId', $gestionId)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $r): array => [
            'insId' => (int) $r['insId'],
            'materiaId' => (int) $r['materiaId'],
            'numeroExamen' => (int) $r['numeroExamen'],
            'valor' => (int) $r['valor'],
        ], $rows);
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
