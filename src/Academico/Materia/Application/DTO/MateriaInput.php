<?php

declare(strict_types=1);

namespace App\Academico\Materia\Application\DTO;

final readonly class MateriaInput
{
    public function __construct(
        public string $codigo,
        public string $nombre,
        public ?string $descripcion,
        public ?string $area,
        public ?int $actorUserId,
    ) {
    }
}
