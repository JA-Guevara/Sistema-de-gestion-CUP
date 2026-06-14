<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure\Persistence;

use App\Inscripcion\Domain\Catalog\TipoPostulacion;
use App\Inscripcion\Domain\Entity\Inscripcion;
use App\Notas\Domain\Entity\AsignacionDocente;
use App\Notas\Domain\Entity\AsignacionGrupo;
use App\Notas\Domain\Entity\Nota;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Consultas de agregación para el Dashboard analítico / Reportes.
 * Solo lectura: arma datasets para KPIs y gráficos a partir de las notas,
 * inscripciones y asignaciones de una gestión, con filtros opcionales.
 */
final readonly class ReporteRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * Una fila por examen rendido de los estudiantes de la gestión, con datos
     * del alumno y de la materia. Se agrega luego en PHP por (alumno, materia).
     *
     * @return list<array<string, mixed>>
     */
    public function notasEstudiantes(int $gestionId, ?int $carreraId, ?int $materiaId, ?int $docenteId = null): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('IDENTITY(n.inscripcion) AS insId, i.ci AS ci, i.nombres AS nombres, i.apellidos AS apellidos, IDENTITY(i.carrera) AS carreraId, c.nombre AS carreraNombre, IDENTITY(n.materia) AS materiaId, m.nombre AS materiaNombre, n.valor AS valor')
            ->from(Nota::class, 'n')
            ->join('n.inscripcion', 'i')
            ->join('n.materia', 'm')
            ->leftJoin('i.carrera', 'c')
            ->where('i.gestion = :gestion')
            ->andWhere('i.tipo = :tipo')
            ->setParameter('gestion', $gestionId)
            ->setParameter('tipo', TipoPostulacion::ESTUDIANTE);

        if ($carreraId !== null) {
            $qb->andWhere('i.carrera = :carrera')->setParameter('carrera', $carreraId);
        }
        if ($materiaId !== null) {
            $qb->andWhere('n.materia = :materia')->setParameter('materia', $materiaId);
        }
        if ($docenteId !== null) {
            // Solo alumnos cuyo (materia, grupo) lo dicta ese docente en la gestión.
            $qb->join(AsignacionGrupo::class, 'ag', 'WITH', 'ag.inscripcion = n.inscripcion AND ag.materia = n.materia')
                ->join(AsignacionDocente::class, 'ad', 'WITH', 'ad.gestion = :gestion AND ad.materia = ag.materia AND ad.grupo = ag.grupo AND ad.docente = :docente')
                ->setParameter('docente', $docenteId);
        }

        return $qb->getQuery()->getArrayResult();
    }

    public function totalInscritosEstudiantes(int $gestionId, ?int $carreraId): int
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(Inscripcion::class, 'i')
            ->where('i.gestion = :gestion')
            ->andWhere('i.tipo = :tipo')
            ->setParameter('gestion', $gestionId)
            ->setParameter('tipo', TipoPostulacion::ESTUDIANTE);

        if ($carreraId !== null) {
            $qb->andWhere('i.carrera = :carrera')->setParameter('carrera', $carreraId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /** @return list<array{carrera: string, total: int|string}> */
    public function inscritosPorCarrera(int $gestionId): array
    {
        return $this->em->createQueryBuilder()
            ->select('c.nombre AS carrera, COUNT(i.id) AS total')
            ->from(Inscripcion::class, 'i')
            ->join('i.carrera', 'c')
            ->where('i.gestion = :gestion')
            ->andWhere('i.tipo = :tipo')
            ->setParameter('gestion', $gestionId)
            ->setParameter('tipo', TipoPostulacion::ESTUDIANTE)
            ->groupBy('c.id')->addGroupBy('c.nombre')
            ->orderBy('total', 'DESC')
            ->getQuery()->getArrayResult();
    }

    /** @return list<array<string, mixed>> Carga docente: grupos distintos por docente. */
    public function docentesPorGrupo(int $gestionId, ?int $materiaId, ?int $docenteId): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('u.firstName AS firstName, u.lastName AS lastName, COUNT(DISTINCT a.grupo) AS grupos')
            ->from(AsignacionDocente::class, 'a')
            ->join('a.docente', 'u')
            ->where('a.gestion = :gestion')
            ->setParameter('gestion', $gestionId);

        if ($materiaId !== null) {
            $qb->andWhere('a.materia = :materia')->setParameter('materia', $materiaId);
        }
        if ($docenteId !== null) {
            $qb->andWhere('a.docente = :docente')->setParameter('docente', $docenteId);
        }

        return $qb->groupBy('u.id')->addGroupBy('u.firstName')->addGroupBy('u.lastName')
            ->orderBy('grupos', 'DESC')
            ->getQuery()->getArrayResult();
    }

    public function totalDocentes(int $gestionId): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(DISTINCT a.docente)')
            ->from(AsignacionDocente::class, 'a')
            ->where('a.gestion = :gestion')
            ->setParameter('gestion', $gestionId)
            ->getQuery()->getSingleScalarResult();
    }

    /** @return list<array{grupoId: int|string, grupo: string, insId: int|string}> */
    public function asignacionEstudianteGrupo(int $gestionId): array
    {
        return $this->em->createQueryBuilder()
            ->select('IDENTITY(ag.grupo) AS grupoId, g.codigo AS grupo, IDENTITY(ag.inscripcion) AS insId')
            ->from(AsignacionGrupo::class, 'ag')
            ->join('ag.grupo', 'g')
            ->where('g.gestion = :gestion')
            ->setParameter('gestion', $gestionId)
            ->getQuery()->getArrayResult();
    }

    /** @return list<array{id: int|string, nombre: string}> */
    public function carrerasConEstudiantes(int $gestionId): array
    {
        return $this->em->createQueryBuilder()
            ->select('DISTINCT c.id AS id, c.nombre AS nombre')
            ->from(Inscripcion::class, 'i')
            ->join('i.carrera', 'c')
            ->where('i.gestion = :gestion')
            ->andWhere('i.tipo = :tipo')
            ->setParameter('gestion', $gestionId)
            ->setParameter('tipo', TipoPostulacion::ESTUDIANTE)
            ->orderBy('c.nombre', 'ASC')
            ->getQuery()->getArrayResult();
    }

    /** @return list<array{id: int|string, nombre: string}> */
    public function materiasConNotas(int $gestionId): array
    {
        return $this->em->createQueryBuilder()
            ->select('DISTINCT m.id AS id, m.nombre AS nombre')
            ->from(Nota::class, 'n')
            ->join('n.materia', 'm')
            ->join('n.inscripcion', 'i')
            ->where('i.gestion = :gestion')
            ->setParameter('gestion', $gestionId)
            ->orderBy('m.nombre', 'ASC')
            ->getQuery()->getArrayResult();
    }

    /** @return list<array<string, mixed>> */
    public function docentesDeGestion(int $gestionId): array
    {
        return $this->em->createQueryBuilder()
            ->select('DISTINCT u.id AS id, u.firstName AS firstName, u.lastName AS lastName')
            ->from(AsignacionDocente::class, 'a')
            ->join('a.docente', 'u')
            ->where('a.gestion = :gestion')
            ->setParameter('gestion', $gestionId)
            ->orderBy('u.lastName', 'ASC')
            ->getQuery()->getArrayResult();
    }
}
