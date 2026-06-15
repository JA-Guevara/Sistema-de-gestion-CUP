<?php

declare(strict_types=1);

namespace App\Notas\Infrastructure\Persistence;

use App\Auth\Entity\User;
use App\Notas\Domain\Entity\AsignacionDocente;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AsignacionDocenteRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(AsignacionDocente $asignacion): void
    {
        $this->entityManager->persist($asignacion);
        $this->entityManager->flush();
    }

    public function remove(AsignacionDocente $asignacion): void
    {
        $this->entityManager->remove($asignacion);
        $this->entityManager->flush();
    }

    public function findById(int $id): ?AsignacionDocente
    {
        return $this->entityManager->find(AsignacionDocente::class, $id);
    }

    public function findByMateriaGrupoGestion(int $materiaId, int $grupoId, int $gestionId): ?AsignacionDocente
    {
        return $this->entityManager
            ->getRepository(AsignacionDocente::class)
            ->findOneBy(['materia' => $materiaId, 'grupo' => $grupoId, 'gestion' => $gestionId]);
    }

    /** @return list<AsignacionDocente> */
    public function listByGestion(int $gestionId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('a', 'm', 'gr', 'd')
            ->from(AsignacionDocente::class, 'a')
            ->join('a.materia', 'm')
            ->join('a.grupo', 'gr')
            ->join('a.docente', 'd')
            ->where('a.gestion = :gestionId')
            ->setParameter('gestionId', $gestionId)
            ->orderBy('m.nombre', 'ASC')
            ->addOrderBy('gr.codigo', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<AsignacionDocente> */
    public function listByDocenteAndGestion(int $docenteId, int $gestionId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('a', 'm', 'gr')
            ->from(AsignacionDocente::class, 'a')
            ->join('a.materia', 'm')
            ->join('a.grupo', 'gr')
            ->where('a.docente = :docenteId')
            ->andWhere('a.gestion = :gestionId')
            ->setParameter('docenteId', $docenteId)
            ->setParameter('gestionId', $gestionId)
            ->orderBy('m.nombre', 'ASC')
            ->addOrderBy('gr.codigo', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Ids de las MATERIAS DISTINTAS que dicta un docente en una gestion.
     * La misma materia en varios grupos cuenta una sola vez (regla de carga).
     *
     * @return list<int>
     */
    public function materiaIdsByDocente(int $docenteId, int $gestionId): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT IDENTITY(a.materia) AS materiaId')
            ->from(AsignacionDocente::class, 'a')
            ->where('a.docente = :docenteId')
            ->andWhere('a.gestion = :gestionId')
            ->setParameter('docenteId', $docenteId)
            ->setParameter('gestionId', $gestionId)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['materiaId'], $rows);
    }

    /**
     * Ids de los GRUPOS DISTINTOS en los que dicta un docente en una gestion.
     * El mismo grupo con varias materias cuenta una sola vez (regla de carga:
     * maximo 4 grupos por docente).
     *
     * @return list<int>
     */
    public function grupoIdsByDocente(int $docenteId, int $gestionId): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT IDENTITY(a.grupo) AS grupoId')
            ->from(AsignacionDocente::class, 'a')
            ->where('a.docente = :docenteId')
            ->andWhere('a.gestion = :gestionId')
            ->setParameter('docenteId', $docenteId)
            ->setParameter('gestionId', $gestionId)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['grupoId'], $rows);
    }

    public function isDocenteDeMateriaGrupo(int $docenteId, int $materiaId, int $grupoId, int $gestionId): bool
    {
        return $this->entityManager
            ->getRepository(AsignacionDocente::class)
            ->findOneBy([
                'docente' => $docenteId,
                'materia' => $materiaId,
                'grupo' => $grupoId,
                'gestion' => $gestionId,
            ]) !== null;
    }

    /**
     * Usuarios con el rol "Docente" activos, para el selector de asignación.
     *
     * @return list<User>
     */
    public function listDocentes(): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->join('u.roles', 'r')
            ->where('r.name = :rol')
            ->andWhere('r.active = true')
            ->andWhere('u.active = true')
            ->setParameter('rol', 'Docente')
            ->orderBy('u.firstName', 'ASC')
            ->addOrderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
