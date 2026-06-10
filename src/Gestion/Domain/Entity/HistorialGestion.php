<?php

declare(strict_types=1);

namespace App\Gestion\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'gestion_historial')]
#[ORM\Index(name: 'idx_gestion_historial_accion', columns: ['accion'])]
class HistorialGestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Gestion::class, inversedBy: 'historial')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Gestion $gestion;

    #[ORM\Column(length: 60)]
    public string $accion;

    #[ORM\Column(type: 'text')]
    public string $descripcion;

    #[ORM\Column(nullable: true)]
    public ?int $usuarioId = null;

    #[ORM\Column]
    public \DateTimeImmutable $fecha;

    public function __construct()
    {
        $this->fecha = new \DateTimeImmutable();
    }

    public static function create(string $accion, string $descripcion, ?int $usuarioId): self
    {
        $historial = new self();
        $historial->accion = $accion;
        $historial->descripcion = trim($descripcion);
        $historial->usuarioId = $usuarioId;

        return $historial;
    }
}
