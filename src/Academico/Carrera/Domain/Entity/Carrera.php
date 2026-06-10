<?php

declare(strict_types=1);

namespace App\Academico\Carrera\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'carreras')]
#[ORM\UniqueConstraint(name: 'uniq_carreras_codigo', columns: ['codigo'])]
#[ORM\Index(name: 'idx_carreras_estado', columns: ['estado'])]
class Carrera
{
    public const ESTADO_ACTIVA = 'ACTIVA';
    public const ESTADO_INACTIVA = 'INACTIVA';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 30)]
    public string $codigo;

    #[ORM\Column(length: 160)]
    public string $nombre;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $descripcion = null;

    #[ORM\Column(length: 120, nullable: true)]
    public ?string $facultad = null;

    #[ORM\Column(length: 80, nullable: true)]
    public ?string $modalidad = null;

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

    public function rename(string $codigo, string $nombre, ?string $descripcion): void
    {
        $this->codigo = mb_strtoupper(trim($codigo));
        $this->nombre = trim($nombre);
        $this->descripcion = self::nullableText($descripcion);
        $this->touch();
    }

    public function configure(?string $facultad, ?string $modalidad): void
    {
        $this->facultad = self::nullableText($facultad);
        $this->modalidad = self::nullableText($modalidad);
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

    private static function nullableText(?string $value): ?string
    {
        return $value !== null && trim($value) !== '' ? trim($value) : null;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
