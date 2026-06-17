<?php

declare(strict_types=1);

namespace App\Asignacion\Application\UseCase;

use App\Academico\Grupo\Domain\Entity\Grupo;
use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;
use App\Academico\Horario\Domain\Entity\Horario;
use App\Academico\Horario\Infrastructure\Persistence\HorarioRepository;
use App\Academico\Materia\Domain\Catalog\AreaCatalog;
use App\Academico\Materia\Domain\Entity\Materia;
use App\Academico\Materia\Infrastructure\Persistence\MateriaRepository;
use App\Asignacion\Application\DTO\AsignarDocenteInput;
use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Bitacora\Application\EventLog\NotasEvents;
use App\Gestion\Domain\Entity\Gestion;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Inscripcion\Domain\Catalog\TipoPostulacion;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;
use App\Notas\Domain\Entity\AsignacionDocente;
use App\Notas\Domain\Exception\NotaException;
use App\Notas\Infrastructure\Persistence\AsignacionDocenteRepository;

/**
 * Asigna un docente a una materia dentro de un grupo.
 *
 * Regla de carga del CUP: un docente puede dictar en un maximo de 4 GRUPOS
 * distintos. Puede dictar varias materias del mismo grupo (no suma grupo) y la
 * misma materia en varios grupos, siempre que no supere los 4 grupos.
 */
final readonly class AsignarDocenteAGrupo
{
    private const MAX_GRUPOS = 4;

    public function __construct(
        private AsignacionDocenteRepository $asignaciones,
        private MateriaRepository $materias,
        private GrupoRepository $grupos,
        private UserRepository $users,
        private GestionRepository $gestiones,
        private HorarioRepository $horarios,
        private InscripcionRepository $inscripciones,
        private NotasEvents $events,
    ) {
    }

    public function execute(AsignarDocenteInput $input): AsignacionDocente
    {
        $gestion = $this->gestiones->findActive();
        if ($gestion === null) {
            throw new NotaException('No hay una gestion activa en este momento.');
        }

        $materia = $this->materias->findById($input->materiaId);
        if ($materia === null || !$materia->isActive()) {
            throw new NotaException('La materia seleccionada no existe o no esta activa.');
        }

        $grupo = $this->grupos->findById($input->grupoId);
        if ($grupo === null || $grupo->gestion->id !== $gestion->id) {
            throw new NotaException('El grupo seleccionado no existe o no pertenece a la gestion activa.');
        }

        $docente = $this->users->findById($input->docenteId);
        if ($docente === null || !$docente->active) {
            throw new NotaException('El docente seleccionado no existe o esta inactivo.');
        }

        if (!$docente->hasPermission('notas.gestionar') && !$docente->hasPermission('notas.registrar')) {
            throw new NotaException('El usuario seleccionado no tiene el rol Docente.');
        }

        if ($this->asignaciones->findByMateriaGrupoGestion($materia->id, $grupo->id, $gestion->id) !== null) {
            throw new NotaException('Esa materia y grupo ya tienen un docente asignado en esta gestion.');
        }

        $this->assertAreaHabilitada($docente, $materia, $gestion);
        $this->assertDentroDelLimite($docente, $grupo, $gestion);
        $this->assertSinChoqueHorario($docente, $materia, $grupo, $gestion);

        $asignacion = new AsignacionDocente();
        $asignacion->assign($gestion, $materia, $grupo, $docente);
        $this->asignaciones->save($asignacion);

        $this->events->docenteAsignado(
            $this->materiaLabel($materia),
            $grupo->codigo,
            trim($docente->firstName . ' ' . $docente->lastName),
            $input->actorUserId,
        );

        return $asignacion;
    }

    /**
     * El docente no puede dictar en mas de 4 grupos distintos. Asignarle una
     * materia en un grupo donde YA dicta no incrementa el conteo de grupos.
     */
    private function assertDentroDelLimite(User $docente, Grupo $grupo, Gestion $gestion): void
    {
        $grupoIds = $this->asignaciones->grupoIdsByDocente((int) $docente->id, (int) $gestion->id);

        if (!in_array((int) $grupo->id, $grupoIds, true) && count($grupoIds) >= self::MAX_GRUPOS) {
            throw new NotaException(sprintf(
                'El docente ya dicta en %d grupos distintos (maximo %d). No se puede asignar a un grupo nuevo.',
                count($grupoIds),
                self::MAX_GRUPOS,
            ));
        }
    }

    /**
     * El docente solo puede dictar materias de su area de especialidad (declarada
     * y verificada en su postulacion). La validacion se OMITE (permite) si la
     * materia no tiene area o el docente no declaro areas, para no romper datos
     * previos a esta funcionalidad.
     */
    private function assertAreaHabilitada(User $docente, Materia $materia, Gestion $gestion): void
    {
        if ($materia->area === null) {
            return;
        }

        $inscripcion = $this->inscripciones->findConfirmadaByUserAndGestion(
            (int) $docente->id,
            (int) $gestion->id,
            TipoPostulacion::DOCENTE,
        );

        $areas = $inscripcion?->docenteAreas ?? [];
        if ($areas === []) {
            return;
        }

        if (!in_array($materia->area, $areas, true)) {
            throw new NotaException(sprintf(
                'El docente no esta habilitado en el area de la materia %s (area %s). Sus areas: %s.',
                $materia->codigo,
                AreaCatalog::label($materia->area),
                implode(', ', array_map(static fn (string $a): string => AreaCatalog::label($a), $areas)),
            ));
        }
    }

    private function materiaLabel(Materia $materia): string
    {
        return sprintf('%s - %s', $materia->codigo, $materia->nombre);
    }

    /**
     * Verifica que las franjas horarias del grupo+materia que se va a asignar no
     * choquen (mismo dia y rango) con las de las demas asignaciones del docente.
     * Si aun no hay franjas creadas para alguna de las dos, no se puede evaluar
     * y no se bloquea (el choque se evalua cuando ambas tienen horario).
     */
    private function assertSinChoqueHorario(User $docente, Materia $materia, Grupo $grupo, Gestion $gestion): void
    {
        $nuevas = $this->horarios->listByGrupoAndMateria((int) $grupo->id, (int) $materia->id);
        if ($nuevas === []) {
            return;
        }

        foreach ($this->asignaciones->listByDocenteAndGestion((int) $docente->id, (int) $gestion->id) as $existente) {
            $franjas = $this->horarios->listByGrupoAndMateria((int) $existente->grupo->id, (int) $existente->materia->id);
            foreach ($franjas as $franja) {
                foreach ($nuevas as $nueva) {
                    if ($this->seSolapan($franja, $nueva)) {
                        throw new NotaException(sprintf(
                            'Choque de horario: el docente ya dicta en el grupo %s el %s en ese rango horario.',
                            $existente->grupo->codigo,
                            mb_strtolower($franja->dia),
                        ));
                    }
                }
            }
        }
    }

    private function seSolapan(Horario $a, Horario $b): bool
    {
        return mb_strtoupper($a->dia) === mb_strtoupper($b->dia)
            && $a->horaInicio < $b->horaFin
            && $b->horaInicio < $a->horaFin;
    }
}
