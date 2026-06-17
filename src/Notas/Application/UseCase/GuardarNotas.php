<?php

declare(strict_types=1);

namespace App\Notas\Application\UseCase;

use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;
use App\Academico\Materia\Infrastructure\Persistence\MateriaRepository;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Bitacora\Application\EventLog\NotasEvents;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Notas\Application\DTO\GuardarNotasInput;
use App\Notas\Application\Security\PlanillaAccessPolicy;
use App\Notas\Domain\Entity\Nota;
use App\Notas\Domain\Exception\NotaException;
use App\Notas\Infrastructure\Persistence\AsignacionGrupoRepository;
use App\Notas\Infrastructure\Persistence\NotaRepository;

final readonly class GuardarNotas
{
    private const DEFAULT_EXAMENES = 3;

    public function __construct(
        private MateriaRepository $materias,
        private GrupoRepository $grupos,
        private GestionRepository $gestiones,
        private AsignacionGrupoRepository $asignacionesGrupo,
        private NotaRepository $notas,
        private UserRepository $users,
        private PlanillaAccessPolicy $accessPolicy,
        private NotasEvents $events,
    ) {
    }

    public function execute(GuardarNotasInput $input): int
    {
        $gestion = $this->gestiones->findActive();
        if ($gestion === null) {
            throw new NotaException('No hay una gestion activa en este momento.');
        }

        $materia = $this->materias->findById($input->materiaId);
        if ($materia === null) {
            throw new NotaException('La materia seleccionada no existe.');
        }

        $grupo = $this->grupos->findById($input->grupoId);
        if ($grupo === null) {
            throw new NotaException('El grupo seleccionado no existe.');
        }

        // Autorizacion a nivel de objeto: solo el docente asignado (o admin) edita.
        $this->accessPolicy->assertPuede($input->actorUserId, $materia->id, $grupo->id, (int) $gestion->id);

        $cantidadExamenes = max(1, $gestion->configuracion?->cantidadExamenes ?? self::DEFAULT_EXAMENES);
        $actor = $input->actorUserId !== null ? $this->users->findById($input->actorUserId) : null;

        // Solo se aceptan notas de los estudiantes realmente asignados a este grupo en esta materia.
        $inscritosPorId = [];
        foreach ($this->asignacionesGrupo->listByMateriaAndGrupo($materia->id, $grupo->id) as $asignacion) {
            $inscritosPorId[$asignacion->inscripcion->id] = $asignacion->inscripcion;
        }

        $guardadas = 0;
        foreach ($input->valores as $inscripcionId => $examenes) {
            $inscripcionId = (int) $inscripcionId;
            if (!isset($inscritosPorId[$inscripcionId]) || !is_array($examenes)) {
                continue;
            }

            $inscripcion = $inscritosPorId[$inscripcionId];

            foreach ($examenes as $numeroExamen => $valor) {
                $numeroExamen = (int) $numeroExamen;
                if ($numeroExamen < 1 || $numeroExamen > $cantidadExamenes) {
                    continue;
                }

                if ($valor === null || trim((string) $valor) === '') {
                    continue;
                }

                if (!is_numeric($valor)) {
                    throw new NotaException(sprintf('La nota de %s %s debe ser un numero.', $inscripcion->nombres, $inscripcion->apellidos));
                }

                $entero = (int) $valor;
                if ($entero < 0 || $entero > 100) {
                    throw new NotaException(sprintf('La nota de %s %s debe estar entre 0 y 100.', $inscripcion->nombres, $inscripcion->apellidos));
                }

                $nota = $this->notas->findOne($inscripcion->id, $materia->id, $numeroExamen);
                if ($nota === null) {
                    $nota = new Nota();
                    $nota->inscripcion = $inscripcion;
                    $nota->materia = $materia;
                    $nota->numeroExamen = $numeroExamen;
                    $this->notas->persist($nota);
                }

                $nota->setValor($entero, null, $actor);
                $guardadas++;
            }
        }

        if ($guardadas > 0) {
            $this->notas->flush();
            $this->events->notasRegistradas(
                sprintf('%s - %s', $materia->codigo, $materia->nombre),
                $grupo->codigo,
                $guardadas,
                $input->actorUserId,
            );
        }

        return $guardadas;
    }
}
