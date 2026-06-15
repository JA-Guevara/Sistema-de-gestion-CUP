<?php

declare(strict_types=1);

namespace App\Asignacion\Application\UseCase;

use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;
use App\Academico\Materia\Infrastructure\Persistence\MateriaRepository;
use App\Asignacion\Application\DTO\AsignarEstudianteGrupoInput;
use App\Bitacora\Application\EventLog\NotasEvents;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;
use App\Notas\Domain\Entity\AsignacionGrupo;
use App\Notas\Domain\Exception\NotaException;
use App\Notas\Infrastructure\Persistence\AsignacionGrupoRepository;

/**
 * Asigna estudiantes a un GRUPO COMPLETO: cada estudiante queda matriculado en
 * las 4 materias activas del CUP apuntando a ese grupo (asi su planilla, boletin
 * y horario quedan poblados sin tocar el modelo por-materia existente).
 *
 * Sirve para el flujo individual (1 id) y el masivo (N ids). Respeta el cupo del
 * grupo contando estudiantes DISTINTOS; los que excedan el cupo se omiten.
 */
final readonly class AsignarEstudianteAGrupo
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

    /** @return array{asignados:int, omitidos:int} */
    public function execute(AsignarEstudianteGrupoInput $input): array
    {
        $gestion = $this->gestiones->findActive();
        if ($gestion === null) {
            throw new NotaException('No hay una gestion activa en este momento.');
        }

        $grupo = $this->grupos->findById($input->grupoId);
        if ($grupo === null || $grupo->gestion->id !== $gestion->id) {
            throw new NotaException('El grupo seleccionado no existe o no pertenece a la gestion activa.');
        }

        if (!$grupo->isOpen()) {
            throw new NotaException(sprintf('El grupo %s esta cerrado.', $grupo->codigo));
        }

        $materias = $this->materias->listActive();
        if ($materias === []) {
            throw new NotaException('No hay materias activas para asignar al grupo.');
        }

        $inscritosActuales = $this->asignaciones->contarInscritosPorGrupo((int) $gestion->id)[(int) $grupo->id] ?? 0;
        $disponibles = max(0, $grupo->cupo - $inscritosActuales);

        $asignados = 0;
        $omitidos = 0;
        $consumidos = 0;
        $cambios = false;

        foreach (array_values(array_unique($input->inscripcionIds)) as $inscripcionId) {
            $inscripcion = $this->inscripciones->findById((int) $inscripcionId);
            if ($inscripcion === null
                || $inscripcion->gestion->id !== $gestion->id
                || !$inscripcion->esEstudiante()
                || !$inscripcion->isConfirmada()) {
                $omitidos++;
                continue;
            }

            $yaEnGrupo = $this->asignaciones->listByInscripcionAndGrupo((int) $inscripcion->id, (int) $grupo->id) !== [];

            // Solo los estudiantes NUEVOS en el grupo (incl. los que se mueven
            // desde otro grupo) consumen cupo.
            if (!$yaEnGrupo) {
                if ($consumidos >= $disponibles) {
                    $omitidos++;
                    continue;
                }
                $consumidos++;
            }

            $cambioEstudiante = false;
            foreach ($materias as $materia) {
                $existente = $this->asignaciones->findByInscripcionAndMateria((int) $inscripcion->id, (int) $materia->id);
                if ($existente !== null) {
                    if ($existente->grupo->id === $grupo->id) {
                        continue;
                    }
                    $existente->grupo = $grupo; // estaba en otro grupo: se mueve a este.
                    $cambioEstudiante = true;
                } else {
                    $asignacion = new AsignacionGrupo();
                    $asignacion->assign($inscripcion, $materia, $grupo);
                    $this->asignaciones->persist($asignacion);
                    $cambioEstudiante = true;
                }
            }

            if ($cambioEstudiante) {
                $asignados++;
                $cambios = true;
            }
        }

        if ($cambios) {
            $this->asignaciones->flush();
            $this->events->estudiantesAsignados('Todas las materias', $grupo->codigo, $asignados, $input->actorUserId);
        }

        return ['asignados' => $asignados, 'omitidos' => $omitidos];
    }
}
