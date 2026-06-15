<?php

declare(strict_types=1);

namespace App\Asignacion\Application\UseCase;

use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Notas\Application\UseCase\ResumenGruposGestion;
use App\Notas\Infrastructure\Persistence\AsignacionGrupoRepository;

/**
 * Detalle de un grupo para el modulo de asignaciones: su tarjeta academica
 * (turno, aulas, horario, materias, docentes, cupos) y el roster de estudiantes.
 */
final readonly class ShowGrupoDetalle
{
    public function __construct(
        private GestionRepository $gestiones,
        private GrupoRepository $grupos,
        private ResumenGruposGestion $resumenGrupos,
        private AsignacionGrupoRepository $asignacionesGrupo,
    ) {
    }

    /**
     * @return array{
     *     gestion: \App\Gestion\Domain\Entity\Gestion,
     *     grupo: \App\Academico\Grupo\Domain\Entity\Grupo,
     *     tarjeta: array<string, mixed>|null,
     *     roster: list<\App\Inscripcion\Domain\Entity\Inscripcion>
     * }|null
     */
    public function execute(int $grupoId): ?array
    {
        $gestion = $this->gestiones->findActive();
        if ($gestion === null) {
            return null;
        }

        $grupo = $this->grupos->findById($grupoId);
        if ($grupo === null || $grupo->gestion->id !== $gestion->id) {
            return null;
        }

        $tarjeta = null;
        foreach ($this->resumenGrupos->execute((int) $gestion->id) as $item) {
            if ((int) $item['grupo']->id === $grupoId) {
                $tarjeta = $item;
                break;
            }
        }

        return [
            'gestion' => $gestion,
            'grupo' => $grupo,
            'tarjeta' => $tarjeta,
            'roster' => $this->asignacionesGrupo->listEstudiantesDistintosByGrupo($grupoId),
        ];
    }
}
