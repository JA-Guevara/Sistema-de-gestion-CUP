<?php

declare(strict_types=1);

namespace App\Academico\Horario\Infrastructure\Persistence;

use App\Academico\Horario\Domain\Entity\Horario;
use Doctrine\ORM\EntityManagerInterface;

final readonly class HorarioRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Horario $horario): void
    {
        $this->entityManager->persist($horario);
        $this->entityManager->flush();
    }

    /** @param list<Horario> $horarios */
    public function saveMany(array $horarios): void
    {
        foreach ($horarios as $horario) {
            $this->entityManager->persist($horario);
        }

        $this->entityManager->flush();
    }

    public function findById(int $id): ?Horario
    {
        return $this->entityManager->find(Horario::class, $id);
    }

    public function hasOverlap(int $grupoId, int $aulaId, string $dia, \DateTimeImmutable $horaInicio, \DateTimeImmutable $horaFin, ?int $excludeId = null): bool
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('COUNT(h.id)')
            ->from(Horario::class, 'h')
            ->where('h.dia = :dia')
            ->andWhere('h.horaInicio < :horaFin')
            ->andWhere('h.horaFin > :horaInicio')
            ->andWhere('IDENTITY(h.grupo) = :grupoId OR IDENTITY(h.aula) = :aulaId')
            ->setParameter('dia', mb_strtoupper(trim($dia)))
            ->setParameter('horaInicio', $horaInicio)
            ->setParameter('horaFin', $horaFin)
            ->setParameter('grupoId', $grupoId)
            ->setParameter('aulaId', $aulaId);

        if ($excludeId !== null) {
            $query
                ->andWhere('h.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        $count = $query->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    /** @return list<Horario> */
    public function listAll(): array
    {
        return $this->entityManager->getRepository(Horario::class)->findBy([], ['dia' => 'ASC', 'horaInicio' => 'ASC']);
    }

    /** @return list<Horario> */
    public function listByGrupo(int $grupoId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('h')
            ->from(Horario::class, 'h')
            ->where('IDENTITY(h.grupo) = :grupoId')
            ->setParameter('grupoId', $grupoId)
            ->orderBy('h.dia', 'ASC')
            ->addOrderBy('h.horaInicio', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Elimina todos los horarios de un grupo. Usado por la generacion masiva
     * en modo "reemplazar". Devuelve la cantidad de filas borradas.
     */
    public function deleteByGrupo(int $grupoId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->delete(Horario::class, 'h')
            ->where('IDENTITY(h.grupo) = :grupoId')
            ->setParameter('grupoId', $grupoId)
            ->getQuery()
            ->execute();
    }

    public function remove(Horario $horario): void
    {
        $this->entityManager->remove($horario);
        $this->entityManager->flush();
    }

    /**
     * Elimina todas las franjas de una materia dentro de un grupo.
     * Devuelve la cantidad de filas borradas.
     */
    public function deleteByGrupoAndMateria(int $grupoId, int $materiaId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->delete(Horario::class, 'h')
            ->where('IDENTITY(h.grupo) = :grupoId')
            ->andWhere('IDENTITY(h.materia) = :materiaId')
            ->setParameter('grupoId', $grupoId)
            ->setParameter('materiaId', $materiaId)
            ->getQuery()
            ->execute();
    }
}
