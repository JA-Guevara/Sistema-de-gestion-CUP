<?php

declare(strict_types=1);

namespace App\Academico\Aula\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'aulas')]
#[ORM\UniqueConstraint(name: 'uniq_aulas_codigo', columns: ['codigo'])]
class Aula
{
    public const ESTADO_ACTIVA = 'ACTIVA';
    public const ESTADO_INACTIVA = 'INACTIVA';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 30)]
    public string $codigo;

    #[ORM\Column(length: 120)]
    public string $nombre;

    #[ORM\Column]
    public int $piso;

    #[ORM\Column]
    public int $capacidad;

    #[ORM\Column(length: 160, nullable: true)]
    public ?string $ubicacion = null;

    #[ORM\Column(length: 20)]
    public string $estado = self::ESTADO_ACTIVA;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function updateData(string $codigo, string $nombre, int $piso, int $capacidad, ?string $ubicacion): void
    {
        $this->codigo = mb_strtoupper(trim($codigo));
        $this->nombre = trim($nombre);
        $this->piso = $piso;
        $this->capacidad = $capacidad;
        $this->ubicacion = $ubicacion !== null && trim($ubicacion) !== '' ? trim($ubicacion) : null;
        $this->touch();
    }

    public function activate(): void
    {
        $this->estado = self::ESTADO_ACTIVA;
        $this->touch();
    }

    public function deactivate(): void
    {
        $this->estado = self::ESTADO_INACTIVA;
        $this->touch();
    }

    public function isActive(): bool
    {
        return $this->estado === self::ESTADO_ACTIVA;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
