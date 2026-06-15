<?php

declare(strict_types=1);

namespace App\Notas\Application\Security;

use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Notas\Domain\Exception\NotaException;
use App\Notas\Infrastructure\Persistence\AsignacionDocenteRepository;

/**
 * Autorizacion a nivel de OBJETO para la planilla de notas (cierra los IDOR de
 * /notas/materia/{materiaId}/grupo/{grupoId}/...). Un docente solo puede
 * ver / editar / exportar / importar las notas de las materias+grupos que tiene
 * ASIGNADOS en la gestion activa. Admin y coordinador (notas.asignar /
 * asignaciones.gestionar) tienen acceso total.
 *
 * Centralizar aqui la verificacion garantiza que NO se confie solo en la UI:
 * el control vive en el backend, en cada caso de uso que toca la planilla.
 */
final readonly class PlanillaAccessPolicy
{
    public function __construct(
        private UserRepository $users,
        private AsignacionDocenteRepository $asignaciones,
    ) {
    }

    public function assertPuede(?int $actorUserId, int $materiaId, int $grupoId, int $gestionId): void
    {
        $actor = $actorUserId !== null ? $this->users->findById($actorUserId) : null;
        if ($actor === null) {
            throw new NotaException('No autorizado.');
        }

        // Admin / coordinador: acceso total a cualquier planilla.
        if ($actor->hasPermission('asignaciones.gestionar') || $actor->hasPermission('notas.asignar')) {
            return;
        }

        // Docente: solo su propia materia + grupo en la gestion activa.
        if (!$this->asignaciones->isDocenteDeMateriaGrupo($actorUserId, $materiaId, $grupoId, $gestionId)) {
            throw new NotaException('No tienes asignada esa materia y grupo: no puedes acceder a su planilla de notas.');
        }
    }
}
