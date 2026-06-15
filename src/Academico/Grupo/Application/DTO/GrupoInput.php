<?php

declare(strict_types=1);

namespace App\Academico\Grupo\Application\DTO;

final readonly class GrupoInput
{
    public function __construct(
        public int $gestionId,
        public string $codigo,
        public string $nombre,
        public int $cupo,
        public int $inscritosEstimados,
        public ?int $turnoId,
        public ?int $actorUserId,
    ) {
    }
}
