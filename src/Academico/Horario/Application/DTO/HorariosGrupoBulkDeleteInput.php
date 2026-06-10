<?php

declare(strict_types=1);

namespace App\Academico\Horario\Application\DTO;

final readonly class HorariosGrupoBulkDeleteInput
{
    /**
     * @param list<int> $grupoIds
     */
    public function __construct(
        public array $grupoIds,
        public ?int $actorUserId,
    ) {
    }
}
