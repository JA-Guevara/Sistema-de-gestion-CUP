<?php

declare(strict_types=1);

namespace App\Academico\Aula\Application\DTO;

final readonly class AulaInput
{
    public function __construct(
        public string $codigo,
        public string $nombre,
        public int $piso,
        public int $capacidad,
        public ?string $ubicacion,
        public ?int $actorUserId,
    ) {
    }
}
