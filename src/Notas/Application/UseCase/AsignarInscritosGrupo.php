<?php

declare(strict_types=1);

namespace App\Notas\Application\UseCase;

use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;
use App\Academico\Materia\Infrastructure\Persistence\MateriaRepository;
use App\Bitacora\Application\EventLog\NotasEvents;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;
use App\Notas\Application\DTO\AsignarGrupoInput;
use App\Notas\Domain\Entity\AsignacionGrupo;
use App\Notas\Domain\Exception\NotaException;
use App\Notas\Infrastructure\Persistence\AsignacionGrupoRepository;

final readonly class AsignarInscritosGrupo
{
    public function __construct(
        private AsignacionGrupoRepository $asignaciones,
        private MateriaRepository $materias,
        private GrupoRepository $grupos,
        private InscripcionRepository $inscripciones,
        private GestionRepository $gestiones,
        private NotasEvents $events,
    ) {
    }

    public function execute(AsignarGrupoInput $input): int
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

        $asignados = 0;
        foreach ($input->inscripcionIds as $inscripcionId) {
            $inscripcion = $this->inscripciones->findById((int) $inscripcionId);
            if ($inscripcion === null || $inscripcion->gestion->id !== $gestion->id) {
                continue;
            }

            $existente = $this->asignaciones->findByInscripcionAndMateria($inscripcion->id, $materia->id);
            if ($existente !== null) {
                if ($existente->grupo->id === $grupo->id) {
                    continue;
                }
                // Ya estaba en otro grupo para esta materia: lo movemos.
                $existente->grupo = $grupo;
            } else {
                $asignacion = new AsignacionGrupo();
                $asignacion->assign($inscripcion, $materia, $grupo);
                $this->asignaciones->persist($asignacion);
            }

            $asignados++;
        }

        if ($asignados > 0) {
            $this->asignaciones->flush();
            $this->events->estudiantesAsignados(
                sprintf('%s - %s', $materia->codigo, $materia->nombre),
                $grupo->codigo,
                $asignados,
                $input->actorUserId,
            );
        }

        return $asignados;
    }
}
