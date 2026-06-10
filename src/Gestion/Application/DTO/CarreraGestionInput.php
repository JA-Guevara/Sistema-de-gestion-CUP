<?php

declare(strict_types=1);

namespace App\Gestion\Application\DTO;

final readonly class CarreraGestionInput
{
    public function __construct(
        public int $carreraId,
        public bool $habilitada,
        public int $cupoCarrera,
    ) {
    }
}
