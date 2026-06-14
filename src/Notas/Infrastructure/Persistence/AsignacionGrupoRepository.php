<?php

declare(strict_types=1);

namespace App\Notas\Infrastructure\Persistence;

use App\Notas\Domain\Entity\AsignacionGrupo;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AsignacionGrupoRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function persist(AsignacionGrupo $asignacion): void
    {
        $this->entityManager->persist($asignacion);
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }

    public function remove(AsignacionGrupo $asignacion): void
    {
        $this->entityManager->remove($asignacion);
        $this->entityManager->flush();
    }

    public function findById(int $id): ?AsignacionGrupo
    {
        return $this->entityManager->find(AsignacionGrupo::class, $id);
    }

    public function findByInscripcionAndMateria(int $inscripcionId, int $materiaId): ?AsignacionGrupo
    {
        return $this->entityManager
            ->getRepository(AsignacionGrupo::class)
            ->findOneBy(['inscripcion' => $inscripcionId, 'materia' => $materiaId]);
    }

    /**
     * Estudiantes (inscripciones) asignados a un grupo en una materia.
     *
     * @return list<AsignacionGrupo>
     */
    public function listByMateriaAndGrupo(int $materiaId, int $grupoId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('a', 'i')
            ->from(AsignacionGrupo::class, 'a')
            ->join('a.inscripcion', 'i')
            ->where('a.materia = :materiaId')
            ->andWhere('a.grupo = :grupoId')
            ->setParameter('materiaId', $materiaId)
            ->setParameter('grupoId', $grupoId)
            ->orderBy('i.apellidos', 'ASC')
            ->addOrderBy('i.nombres', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Todas las asignaciones estudiante-grupo de una materia en una gestión.
     *
     * @return list<AsignacionGrupo>
     */
    public function listByMateriaAndGestion(int $materiaId, int $gestionId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('a', 'i', 'gr')
            ->from(AsignacionGrupo::class, 'a')
            ->join('a.inscripcion', 'i')
            ->join('a.grupo', 'gr')
            ->where('a.materia = :materiaId')
            ->andWhere('i.gestion = :gestionId')
            ->setParameter('materiaId', $materiaId)
            ->setParameter('gestionId', $gestionId)
            ->orderBy('gr.codigo', 'ASC')
            ->addOrderBy('i.apellidos', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Grupos del estudiante por materia (para el boletín).
     *
     * @return list<AsignacionGrupo>
     */
    public function listByInscripcion(int $inscripcionId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('a', 'm', 'gr')
            ->from(AsignacionGrupo::class, 'a')
            ->join('a.materia', 'm')
            ->join('a.grupo', 'gr')
            ->where('a.inscripcion = :inscripcionId')
            ->setParameter('inscripcionId', $inscripcionId)
            ->orderBy('m.nombre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
