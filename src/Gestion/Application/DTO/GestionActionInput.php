<?php

declare(strict_types=1);

namespace App\Gestion\Application\DTO;

final readonly class GestionActionInput
{
    public function __construct(
        public int $gestionId,
        public ?int $actorUserId,
    ) {
    }
}
