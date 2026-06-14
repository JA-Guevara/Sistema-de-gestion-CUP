<?php

declare(strict_types=1);

namespace App\Notas\Application\UseCase;

use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;
use App\Academico\Materia\Domain\Entity\Materia;
use App\Academico\Materia\Infrastructure\Persistence\MateriaRepository;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Bitacora\Application\EventLog\NotasEvents;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Notas\Application\DTO\AsignacionInput;
use App\Notas\Domain\Entity\AsignacionDocente;
use App\Notas\Domain\Exception\NotaException;
use App\Notas\Infrastructure\Persistence\AsignacionDocenteRepository;

final readonly class AsignarDocente
{
    public function __construct(
        private AsignacionDocenteRepository $asignaciones,
        private MateriaRepository $materias,
        private GrupoRepository $grupos,
        private UserRepository $users,
        private GestionRepository $gestiones,
        private NotasEvents $events,
    ) {
    }

    public function execute(AsignacionInput $input): AsignacionDocente
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

        if (!$docente->hasPermission('notas.registrar')) {
            throw new NotaException('El usuario seleccionado no tiene el rol Docente.');
        }

        if ($this->asignaciones->findByMateriaGrupoGestion($materia->id, $grupo->id, $gestion->id) !== null) {
            throw new NotaException('Esa materia y grupo ya tienen un docente asignado en esta gestion.');
        }

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

    private function materiaLabel(Materia $materia): string
    {
        return sprintf('%s - %s', $materia->codigo, $materia->nombre);
    }
}
