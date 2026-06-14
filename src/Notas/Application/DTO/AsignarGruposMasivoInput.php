<?php

declare(strict_types=1);

namespace App\Notas\Application\DTO;

final readonly class AsignarGruposMasivoInput
{
    /** @param list<int> $grupoIds */
    public function __construct(
        public int $materiaId,
        public array $grupoIds,
        public ?string $turnoPreferencia,
        public bool $reasignarExistentes,
        public ?int $actorUserId,
    ) {
    }
}
