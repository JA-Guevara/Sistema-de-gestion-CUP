<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\DTO;

final readonly class RechazarInscripcionesBulkInput
{
    /** @param list<int> $ids */
    public function __construct(
        public array $ids,
        public ?string $motivo,
        public ?int $actorUserId,
    ) {
    }
}
