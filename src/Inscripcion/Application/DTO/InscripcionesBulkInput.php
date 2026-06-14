<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\DTO;

final readonly class InscripcionesBulkInput
{
    /** @param list<int> $ids */
    public function __construct(
        public array $ids,
        public ?int $actorUserId,
    ) {
    }
}
