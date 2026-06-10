<?php

declare(strict_types=1);

namespace App\Academico\Aula\Application\DTO;

final readonly class AulasBulkStateInput
{
    /**
     * @param list<int> $aulaIds
     */
    public function __construct(
        public array $aulaIds,
        public string $accion,
        public ?int $actorUserId,
    ) {
    }
}
