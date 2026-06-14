<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Bitacora\Application\EventLog\InscripcionEvents;
use App\Inscripcion\Domain\Catalog\TipoPostulacion;
use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;
use App\Usuario\Infrastructure\Persistence\RoleRepository;

/**
 * Confirma una postulacion VALIDADA: la pasa a CONFIRMADA y asigna al usuario
 * el rol correspondiente (Estudiante o Docente). "Confirmar = asignar rol":
 * el rol auto-habilita los modulos via PermissionGuard + menu filtrado.
 *
 * Reglas: solo desde VALIDADA; una sola postulacion CONFIRMADA por CI + tipo +
 * gestion (existeConfirmadaPorCiTipo).
 */
final readonly class ConfirmarInscripcion
{
    private const ROL_POR_TIPO = [
        TipoPostulacion::ESTUDIANTE => 'Estudiante',
        TipoPostulacion::DOCENTE => 'Docente',
    ];

    public function __construct(
        private InscripcionRepository $inscripciones,
        private RoleRepository $roles,
        private UserRepository $users,
        private InscripcionEvents $events,
    ) {
    }

    public function execute(int $inscripcionId, ?int $actorUserId): void
    {
        $inscripcion = $this->inscripciones->findById($inscripcionId);
        if ($inscripcion === null) {
            throw new InscripcionException('La postulacion no existe.');
        }

        if (!$inscripcion->isValidada()) {
            throw new InscripcionException('Solo puedes confirmar una postulacion con la documentacion validada (aprobada).');
        }

        if ($this->inscripciones->existeConfirmadaPorCiTipo($inscripcion->ci, $inscripcion->tipo, $inscripcion->gestion->id)) {
            throw new InscripcionException('Ya existe una postulacion confirmada para este CI y tipo en esta gestion.');
        }

        $rolNombre = self::ROL_POR_TIPO[$inscripcion->tipo] ?? null;
        if ($rolNombre === null) {
            throw new InscripcionException('Tipo de postulacion desconocido.');
        }

        $rol = $this->roles->findByName($rolNombre);
        if ($rol === null) {
            throw new InscripcionException(sprintf('El rol "%s" no existe. Ejecuta las migraciones de roles.', $rolNombre));
        }

        $inscripcion->confirmar($actorUserId);

        // Asignacion idempotente del rol (se conserva el rol Postulante existente).
        $user = $inscripcion->user;
        if (!$user->hasRole($rolNombre)) {
            $user->syncRoles(array_merge($user->roles(), [$rol]));
        }

        $this->inscripciones->flush();

        $this->events->confirmada($inscripcion->ci, TipoPostulacion::label($inscripcion->tipo), $rolNombre, $actorUserId);
    }
}
