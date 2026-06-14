<?php

declare(strict_types=1);

namespace App\Inscripcion\Domain\Entity;

use App\Inscripcion\Domain\Catalog\EstadoDocumento;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'inscripcion_documentos')]
class Documento
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Inscripcion::class, inversedBy: 'documentos')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Inscripcion $inscripcion;

    #[ORM\Column(length: 80)]
    public string $nombre;

    #[ORM\Column(length: 255)]
    public string $archivoRuta;

    #[ORM\Column(length: 20)]
    public string $archivoExtension;

    #[ORM\Column]
    public int $archivoTamanio;

    /** PENDIENTE | APROBADO | OBSERVADO */
    #[ORM\Column(length: 20)]
    public string $estado = EstadoDocumento::PENDIENTE;

    /** Observación del revisor cuando el documento está mal/incompleto. */
    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $observacion = null;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function revisar(string $estado, ?string $observacion): void
    {
        $this->estado = $estado;
        $this->observacion = $observacion !== null && trim($observacion) !== '' ? trim($observacion) : null;
    }

    public function isAprobado(): bool
    {
        return $this->estado === EstadoDocumento::APROBADO;
    }

    public function isObservado(): bool
    {
        return $this->estado === EstadoDocumento::OBSERVADO;
    }
}
