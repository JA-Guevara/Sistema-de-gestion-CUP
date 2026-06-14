<?php

declare(strict_types=1);

namespace App\Inscripcion\Domain\Entity;

use App\Inscripcion\Domain\Catalog\EstadoVerificacion;
use Doctrine\ORM\Mapping as ORM;

/**
 * Una linea del acta de control de recepcion presencial: el estado de un
 * requisito documental para una postulacion concreta. La administracion la
 * marca el dia de la cita (Entregado / Observado / No presento).
 */
#[ORM\Entity]
#[ORM\Table(name: 'inscripcion_verificaciones')]
#[ORM\UniqueConstraint(name: 'uniq_verificacion_inscripcion_requisito', columns: ['inscripcion_id', 'requisito_codigo'])]
class VerificacionDocumento
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Inscripcion::class, inversedBy: 'verificaciones')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Inscripcion $inscripcion;

    /** Codigo del requisito en RequisitoCatalog (ci, bachiller, titulo, ...). */
    #[ORM\Column(length: 40)]
    public string $requisitoCodigo;

    /** PENDIENTE | ENTREGADO | OBSERVADO | FALTA */
    #[ORM\Column(length: 20)]
    public string $estado = EstadoVerificacion::PENDIENTE;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $observacion = null;

    /** Id del usuario (admin/coordinador) que registro la verificacion. */
    #[ORM\Column(nullable: true)]
    public ?int $verificadoPor = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $verificadoAt = null;

    public function __construct(Inscripcion $inscripcion, string $requisitoCodigo)
    {
        $this->inscripcion = $inscripcion;
        $this->requisitoCodigo = $requisitoCodigo;
    }

    public function registrar(string $estado, ?string $observacion, ?int $actorUserId): void
    {
        $this->estado = $estado;
        $this->observacion = $observacion !== null && trim($observacion) !== '' ? trim($observacion) : null;
        $this->verificadoPor = $actorUserId;
        $this->verificadoAt = new \DateTimeImmutable();
    }

    public function isEntregado(): bool
    {
        return $this->estado === EstadoVerificacion::ENTREGADO;
    }

    public function isObservado(): bool
    {
        return $this->estado === EstadoVerificacion::OBSERVADO;
    }

    public function isFalta(): bool
    {
        return $this->estado === EstadoVerificacion::FALTA;
    }
}
