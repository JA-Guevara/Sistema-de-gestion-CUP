<?php

declare(strict_types=1);

namespace App\Academico\Grupo\Application\DTO;

final readonly class GruposBulkStateInput
{
    /**
     * @param list<int> $grupoIds
     */
    public function __construct(
        public array $grupoIds,
        public string $accion,
        public ?int $actorUserId,
    ) {
    }
}
