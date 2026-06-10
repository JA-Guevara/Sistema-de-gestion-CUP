<?php

declare(strict_types=1);

namespace App\Academico\Aula\Application\DTO;

final readonly class AulasMasivasInput
{
    public function __construct(
        public string $prefijoCodigo,
        public int $piso,
        public int $numeroInicio,
        public int $numeroFin,
        public int $capacidad,
        public ?string $ubicacion,
        public ?int $actorUserId,
    ) {
    }
}
