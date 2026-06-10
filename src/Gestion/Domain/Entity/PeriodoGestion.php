<?php

declare(strict_types=1);

namespace App\Gestion\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'gestion_periodos')]
#[ORM\Index(name: 'idx_gestion_periodos_tipo', columns: ['tipo_periodo'])]
class PeriodoGestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Gestion::class, inversedBy: 'periodos')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Gestion $gestion;

    #[ORM\Column(length: 120)]
    public string $tipoPeriodo;

    #[ORM\Column(type: 'date_immutable')]
    public \DateTimeImmutable $fechaInicio;

    #[ORM\Column(type: 'date_immutable')]
    public \DateTimeImmutable $fechaFin;

    public static function create(string $tipoPeriodo, \DateTimeImmutable $fechaInicio, \DateTimeImmutable $fechaFin): self
    {
        $periodo = new self();
        $periodo->tipoPeriodo = $tipoPeriodo;
        $periodo->fechaInicio = $fechaInicio;
        $periodo->fechaFin = $fechaFin;

        return $periodo;
    }
}
