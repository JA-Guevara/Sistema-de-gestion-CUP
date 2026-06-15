<?php

declare(strict_types=1);

namespace App\Asignacion\Application\UseCase;

use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;
use App\Asignacion\Application\DTO\AsignarDocenteGrupoMateriasInput;
use App\Asignacion\Application\DTO\AsignarDocenteInput;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Notas\Domain\Exception\NotaException;

/**
 * Asigna a un docente VARIAS materias dentro de UN grupo en una sola operacion
 * (flujo "elige grupo y marca sus materias"). Reutiliza AsignarDocenteAGrupo por
 * materia, que aplica el limite de 4 grupos, el choque de horario, el duplicado
 * y la auditoria. Reporta cuantas materias se asignaron y cuantas se omitieron
 * (ya asignadas, choque de horario o limite de grupos).
 */
final readonly class AsignarDocenteAGrupoMaterias
{
    public function __construct(
        private AsignarDocenteAGrupo $asignar,
        private GestionRepository $gestiones,
        private GrupoRepository $grupos,
        private UserRepository $users,
    ) {
    }

    /** @return array{asignados:int, omitidos:int} */
    public function execute(AsignarDocenteGrupoMateriasInput $input): array
    {
        if ($this->gestiones->findActive() === null) {
            throw new NotaException('No hay una gestion activa en este momento.');
        }

        $docente = $this->users->findById($input->docenteId);
        if ($docente === null || !$docente->active) {
            throw new NotaException('El docente seleccionado no existe o esta inactivo.');
        }

        if ($this->grupos->findById($input->grupoId) === null) {
            throw new NotaException('El grupo seleccionado no existe.');
        }

        if ($input->materiaIds === []) {
            throw new NotaException('Selecciona al menos una materia.');
        }

        $asignados = 0;
        $omitidos = 0;
        foreach (array_values(array_unique($input->materiaIds)) as $materiaId) {
            if ($materiaId <= 0) {
                continue;
            }

            try {
                $this->asignar->execute(new AsignarDocenteInput(
                    materiaId: $materiaId,
                    grupoId: $input->grupoId,
                    docenteId: $input->docenteId,
                    actorUserId: $input->actorUserId,
                ));
                $asignados++;
            } catch (NotaException) {
                $omitidos++;
            }
        }

        return ['asignados' => $asignados, 'omitidos' => $omitidos];
    }
}
