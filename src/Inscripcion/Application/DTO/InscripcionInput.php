<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\DTO;

final readonly class InscripcionInput
{
    public function __construct(
        public string $ci,
        public string $nombres,
        public string $apellidos,
        public \DateTimeImmutable $fechaNacimiento,
        public string $sexo,
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
