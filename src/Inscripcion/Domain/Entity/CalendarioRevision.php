<?php

declare(strict_types=1);

namespace App\Inscripcion\Domain\Entity;

use App\Gestion\Domain\Entity\Gestion;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un dia habil de revision de documentos para una gestion, con su capacidad.
 * El admin define estos dias; al PRESENTAR una postulacion, el sistema asigna
 * automaticamente la cita al primer dia habil con cupo (incrementa agendados).
 */
#[ORM\Entity]
#[ORM\Table(name: 'inscripcion_calendario_revision')]
#[ORM\UniqueConstraint(name: 'uniq_calendario_gestion_fecha', columns: ['gestion_id', 'fecha'])]
class CalendarioRevision
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Gestion::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Gestion $gestion;

    #[ORM\Column(type: 'date_immutable')]
    public \DateTimeImmutable $fecha;

    /** Cuantas revisiones caben ese dia. */
    #[ORM\Column]
    public int $capacidad = 0;

    /** Cuantas revisiones ya se agendaron ese dia. */
    #[ORM\Column(options: ['default' => 0])]
    public int $agendados = 0;

    #[ORM\Column(options: ['default' => true])]
    public bool $habilitado = true;

    public function __construct(Gestion $gestion, \DateTimeImmutable $fecha, int $capacidad)
    {
        $this->gestion = $gestion;
        $this->fecha = $fecha->setTime(0, 0);
        $this->capacidad = $capacidad;
    }

    public function disponibles(): int
    {
        return max(0, $this->capacidad - $this->agendados);
    }

    /** Hay cupo si esta habilitado y aun no se llena. */
    public function tieneCupo(): bool
    {
        return $this->habilitado && $this->agendados < $this->capacidad;
    }
}
