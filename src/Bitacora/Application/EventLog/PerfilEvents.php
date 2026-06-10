<?php

declare(strict_types=1);

namespace App\Bitacora\Application\EventLog;

use App\Bitacora\Application\Audit\AuditLogger;
use App\Bitacora\Domain\Catalog\ModuleCatalog;

/** EventLog del módulo Perfil / Usuarios. */
final readonly class PerfilEvents
{
    public function __construct(private AuditLogger $audit)
    {
    }

    /**
     * @param array<string,mixed> $antes
     * @param array<string,mixed> $despues
     */
    public function datosActualizados(int $userId, string $email, array $antes, array $despues): void
    {
        $this->audit->updated(
            ModuleCatalog::USUARIOS,
            sprintf('Usuario %s', $email),
            'Actualizó sus datos de perfil.',
            before: $antes,
            after: $despues,
            userId: $userId,
            userLabel: $email,
        );
    }

    public function passwordCambiado(int $userId, string $email): void
    {
        $this->audit->log(
            ModuleCatalog::USUARIOS,
            \App\Bitacora\Domain\Catalog\ActionCatalog::UPDATE,
            'Cambió su contraseña.',
            entity: sprintf('Usuario %s', $email),
            userId: $userId,
            userLabel: $email,
        );
    }

    public function fotoActualizada(int $userId, string $email): void
    {
        $this->audit->log(
            ModuleCatalog::USUARIOS,
            \App\Bitacora\Domain\Catalog\ActionCatalog::UPDATE,
            'Actualizó su foto de perfil.',
            entity: sprintf('Usuario %s', $email),
            userId: $userId,
            userLabel: $email,
        );
    }
}
