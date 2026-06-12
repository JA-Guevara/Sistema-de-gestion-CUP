<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\DTO;

final readonly class InscripcionEditInput
{
    public function __construct(
        public int $inscripcionId,
        public string $nombres,
        public string $apellidos,
        public ?string $direccion,
        public ?string $telefono,
        public string $email,
        public ?string $colegioProcedencia,
        public ?string $ciudad,
        public bool $tituloBachiller,
        public ?string $turnoPreferencia,
        public ?string $otros,
        public int $carreraId,
        public ?int $actorUserId,
    ) {
    }
}
