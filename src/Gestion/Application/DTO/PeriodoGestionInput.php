<?php

declare(strict_types=1);

namespace App\Gestion\Application\DTO;

final readonly class PeriodoGestionInput
{
    public function __construct(
        public string $tipoPeriodo,
        public ?\DateTimeImmutable $fechaInicio,
        public ?\DateTimeImmutable $fechaFin,
    ) {
    }
}
