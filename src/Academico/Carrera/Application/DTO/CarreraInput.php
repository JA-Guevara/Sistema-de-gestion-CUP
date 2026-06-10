<?php

declare(strict_types=1);

namespace App\Academico\Carrera\Application\DTO;

final readonly class CarreraInput
{
    public function __construct(
        public string $codigo,
        public string $nombre,
        public ?string $descripcion,
        public ?string $facultad,
        public ?string $modalidad,
        public ?int $actorUserId,
    ) {
    }
}
