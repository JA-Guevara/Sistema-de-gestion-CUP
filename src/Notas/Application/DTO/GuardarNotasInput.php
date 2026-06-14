<?php

declare(strict_types=1);

namespace App\Notas\Application\DTO;

final readonly class GuardarNotasInput
{
    /**
     * @param array<int, array<int, int|string|null>> $valores Mapa inscripcionId => [numeroExamen => valor]
     */
    public function __construct(
        public int $materiaId,
        public int $grupoId,
        public array $valores,
        public ?int $actorUserId,
    ) {
    }
}
