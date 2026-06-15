<?php

declare(strict_types=1);

namespace App\Notas\Infrastructure\Persistence;

use App\Inscripcion\Domain\Entity\Inscripcion;
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

    /** @param list<AsignacionGrupo> $asignaciones */
    public function removeMany(array $asignaciones): void
    {
        if ($asignaciones === []) {
            return;
        }

        foreach ($asignaciones as $asignacion) {
            $this->entityManager->remove($asignacion);
        }

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
     * Cantidad de estudiantes DISTINTOS asignados a cada grupo de la gestion
     * (un estudiante asignado a varias materias del mismo grupo cuenta una vez).
     *
     * @return array<int, int> grupoId => cantidad
     */
    public function contarInscritosPorGrupo(int $gestionId): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(a.grupo) AS grupoId, COUNT(DISTINCT a.inscripcion) AS total')
            ->from(AsignacionGrupo::class, 'a')
            ->join('a.inscripcion', 'i')
            ->where('i.gestion = :gestionId')
            ->setParameter('gestionId', $gestionId)
            ->groupBy('a.grupo')
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['grupoId']] = (int) $row['total'];
        }

        return $out;
    }

    /**
     * Filas (una por materia) de un estudiante dentro de un grupo concreto.
     * Sirve para quitar al estudiante del grupo completo (borrar las 4 filas).
     *
     * @return list<AsignacionGrupo>
     */
    public function listByInscripcionAndGrupo(int $inscripcionId, int $grupoId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(AsignacionGrupo::class, 'a')
            ->where('a.inscripcion = :inscripcionId')
            ->andWhere('a.grupo = :grupoId')
            ->setParameter('inscripcionId', $inscripcionId)
            ->setParameter('grupoId', $grupoId)
            ->getQuery()
            ->getResult();
    }

    /**
     * Estudiantes (inscripciones) DISTINTOS asignados a un grupo, sin importar
     * la materia. Para el roster del grupo en el modulo de asignaciones.
     *
     * @return list<Inscripcion>
     */
    public function listEstudiantesDistintosByGrupo(int $grupoId): array
    {
        // Inscripcion como entidad raiz (no se puede SELECT DISTINCT de un alias
        // unido sin elegir la raiz). El grupo se filtra con una subconsulta.
        $dql = sprintf(
            'SELECT i FROM %s i WHERE i.id IN (SELECT IDENTITY(a.inscripcion) FROM %s a WHERE a.grupo = :grupoId) ORDER BY i.apellidos ASC, i.nombres ASC',
            Inscripcion::class,
            AsignacionGrupo::class,
        );

        return $this->entityManager->createQuery($dql)
            ->setParameter('grupoId', $grupoId)
            ->getResult();
    }

    /**
     * Mapa inscripcionId => codigo del grupo actual del estudiante en la gestion
     * (un estudiante esta en un solo grupo). Para mostrar "grupo actual" al
     * reasignar.
     *
     * @return array<int, string>
     */
    public function mapGrupoActualByInscripcion(int $gestionId): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT IDENTITY(a.inscripcion) AS inscId, gr.codigo AS codigo')
            ->from(AsignacionGrupo::class, 'a')
            ->join('a.grupo', 'gr')
            ->join('a.inscripcion', 'i')
            ->where('i.gestion = :gestionId')
            ->setParameter('gestionId', $gestionId)
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['inscId']] = (string) $row['codigo'];
        }

        return $out;
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
