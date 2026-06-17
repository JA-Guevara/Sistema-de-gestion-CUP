<?php

declare(strict_types=1);

namespace App\Academico\Materia\Domain\Entity;

use App\Academico\Materia\Domain\Catalog\AreaCatalog;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'materias')]
#[ORM\UniqueConstraint(name: 'uniq_materias_codigo', columns: ['codigo'])]
class Materia
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

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $descripcion = null;

    /** Area de conocimiento (AreaCatalog). Nullable: las materias previas no la tienen. */
    #[ORM\Column(length: 40, nullable: true)]
    public ?string $area = null;

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

    public function rename(string $codigo, string $nombre, ?string $descripcion, ?string $area = null): void
    {
        $this->codigo = mb_strtoupper(trim($codigo));
        $this->nombre = trim($nombre);
        $this->descripcion = $descripcion !== null && trim($descripcion) !== '' ? trim($descripcion) : null;
        $this->area = AreaCatalog::normalize($area);
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
