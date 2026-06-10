<?php

declare(strict_types=1);

namespace App\Gestion\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'gestion_configuraciones')]
class ConfiguracionGestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'configuracion', targetEntity: Gestion::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Gestion $gestion;

    #[ORM\Column]
    public int $cupoTotal;

    #[ORM\Column]
    public int $maxEstudiantesPorGrupo;

    #[ORM\Column]
    public int $maxGruposPorDocente;

    #[ORM\Column]
    public int $notaMinimaAprobacion;

    #[ORM\Column]
    public int $cantidadExamenes;

    #[ORM\Column(options: ['default' => false])]
    public bool $permiteReinscripcion = false;

    #[ORM\Column(options: ['default' => false])]
    public bool $permiteCambioGrupo = false;

    #[ORM\Column(options: ['default' => true])]
    public bool $generarBitacora = true;

    public static function create(
        int $cupoTotal,
        int $maxEstudiantesPorGrupo,
        int $maxGruposPorDocente,
        int $notaMinimaAprobacion,
        int $cantidadExamenes,
        bool $permiteReinscripcion,
        bool $permiteCambioGrupo,
        bool $generarBitacora,
    ): self {
        $configuracion = new self();
        $configuracion->cupoTotal = $cupoTotal;
        $configuracion->maxEstudiantesPorGrupo = $maxEstudiantesPorGrupo;
        $configuracion->maxGruposPorDocente = $maxGruposPorDocente;
        $configuracion->notaMinimaAprobacion = $notaMinimaAprobacion;
        $configuracion->cantidadExamenes = $cantidadExamenes;
        $configuracion->permiteReinscripcion = $permiteReinscripcion;
        $configuracion->permiteCambioGrupo = $permiteCambioGrupo;
        $configuracion->generarBitacora = $generarBitacora;

        return $configuracion;
    }

    public function updateValues(
        int $cupoTotal,
        int $maxEstudiantesPorGrupo,
        int $maxGruposPorDocente,
        int $notaMinimaAprobacion,
        int $cantidadExamenes,
        bool $permiteReinscripcion,
        bool $permiteCambioGrupo,
        bool $generarBitacora,
    ): void {
        $this->cupoTotal = $cupoTotal;
        $this->maxEstudiantesPorGrupo = $maxEstudiantesPorGrupo;
        $this->maxGruposPorDocente = $maxGruposPorDocente;
        $this->notaMinimaAprobacion = $notaMinimaAprobacion;
        $this->cantidadExamenes = $cantidadExamenes;
        $this->permiteReinscripcion = $permiteReinscripcion;
        $this->permiteCambioGrupo = $permiteCambioGrupo;
        $this->generarBitacora = $generarBitacora;
    }
}
