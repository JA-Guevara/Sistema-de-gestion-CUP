<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\DTO;

final readonly class InscripcionInput
{
    public const ACCION_GUARDAR = 'GUARDAR';
    public const ACCION_PRESENTAR = 'PRESENTAR';

    public function __construct(
        public string $tipo,
        public string $modalidad,
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
        public int $carreraId,
        public int $carreraSegundaId,
        public ?string $docenteProfesion,
        public bool $docenteMaestria,
        public bool $docenteDiplomado,
        public ?string $docenteExperiencia,
        public ?string $otros,
        public string $accion,
        public ?int $actorUserId,
    ) {
    }

    public function esPresentar(): bool
    {
        return $this->accion === self::ACCION_PRESENTAR;
    }
}
