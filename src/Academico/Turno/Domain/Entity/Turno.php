<?php

declare(strict_types=1);

namespace App\Academico\Turno\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Turno: plantilla de tiempo reutilizable (Manana, Tarde, Noche).
 *
 * Define solo la ventana horaria; el aula NO depende del turno (se asigna
 * por grupo). Un mismo turno se reutiliza en muchos grupos.
 */
#[ORM\Entity]
#[ORM\Table(name: 'turnos')]
#[ORM\UniqueConstraint(name: 'uniq_turnos_nombre', columns: ['nombre'])]
class Turno
{
    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_INACTIVO = 'INACTIVO';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 40)]
    public string $nombre;

    #[ORM\Column(type: 'time_immutable')]
    public \DateTimeImmutable $horaInicio;

    #[ORM\Column(type: 'time_immutable')]
    public \DateTimeImmutable $horaFin;

    #[ORM\Column(length: 20)]
    public string $estado = self::ESTADO_ACTIVO;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function configure(string $nombre, \DateTimeImmutable $horaInicio, \DateTimeImmutable $horaFin): void
    {
        $this->nombre = mb_strtoupper(trim($nombre));
        $this->horaInicio = $horaInicio;
        $this->horaFin = $horaFin;
        $this->touch();
    }

    public function activate(): void
    {
        $this->estado = self::ESTADO_ACTIVO;
        $this->touch();
    }

    public function deactivate(): void
    {
        $this->estado = self::ESTADO_INACTIVO;
        $this->touch();
    }

    public function isActive(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
