<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Bitacora\Application\EventLog\InscripcionEvents;
use App\Inscripcion\Domain\Catalog\TipoPostulacion;
use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;
use App\Usuario\Domain\Entity\Role;

/**
 * Anula una postulacion (estado ANULADA). Si estaba CONFIRMADA, retira el rol
 * que se le habia asignado al usuario (Estudiante/Docente). La politica de
 * quien puede anular y desde que estado se aplica en el controlador.
 */
final readonly class AnularInscripcion
{
    private const ROL_POR_TIPO = [
        TipoPostulacion::ESTUDIANTE => 'Estudiante',
        TipoPostulacion::DOCENTE => 'Docente',
    ];

    public function __construct(
        private InscripcionRepository $inscripciones,
        private LiberarCupoRevision $liberarCupoRevision,
        private InscripcionEvents $events,
    ) {
    }

    public function execute(int $inscripcionId, ?int $actorUserId): void
    {
        $inscripcion = $this->inscripciones->findById($inscripcionId);
        if ($inscripcion === null) {
            throw new InscripcionException('La postulacion no existe.');
        }

        if ($inscripcion->esTerminal()) {
            throw new InscripcionException('La postulacion ya esta cerrada (rechazada o anulada).');
        }

        $eraConfirmada = $inscripcion->isConfirmada();
        $tipo = $inscripcion->tipo;

        // Libera el cupo del dia de revision que tenia asignado (si lo tenia).
        $this->liberarCupoRevision->execute($inscripcion);

        $inscripcion->anular();

        if ($eraConfirmada) {
            $rolNombre = self::ROL_POR_TIPO[$tipo] ?? null;
            // Solo se retira el rol si el usuario no tiene otra postulacion
            // CONFIRMADA del mismo tipo (p.ej. confirmado en otra gestion).
            $tieneOtraConfirmada = $this->inscripciones->existeOtraConfirmadaPorUserTipo(
                (int) $inscripcion->user->id,
                $tipo,
                $inscripcionId,
            );
            if ($rolNombre !== null && !$tieneOtraConfirmada) {
                $user = $inscripcion->user;
                if ($user->hasRole($rolNombre)) {
                    $restantes = array_values(array_filter(
                        $user->roles(),
                        static fn (Role $rol): bool => mb_strtolower($rol->name) !== mb_strtolower($rolNombre),
                    ));
                    $user->syncRoles($restantes);
                }
            }
        }

        $this->inscripciones->flush();

        $this->events->anulada($inscripcion->ci, $actorUserId);
    }
}
