<?php

declare(strict_types=1);

namespace App\Notas\Domain\Entity;

use App\Academico\Grupo\Domain\Entity\Grupo;
use App\Academico\Materia\Domain\Entity\Materia;
use App\Inscripcion\Domain\Entity\Inscripcion;
use Doctrine\ORM\Mapping as ORM;

/**
 * Asignación de un estudiante (inscripción) a un grupo PARA una materia.
 * Como el CUP permite grupos distintos por materia, la unicidad es por
 * (inscripción, materia): un estudiante está en un solo grupo por materia.
 */
#[ORM\Entity]
#[ORM\Table(name: 'notas_inscripcion_grupo')]
#[ORM\UniqueConstraint(name: 'uniq_inscripcion_materia_grupo', columns: ['inscripcion_id', 'materia_id'])]
class AsignacionGrupo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Inscripcion::class)]
    #[ORM\JoinColumn(name: 'inscripcion_id', nullable: false, onDelete: 'CASCADE')]
    public Inscripcion $inscripcion;

    #[ORM\ManyToOne(targetEntity: Materia::class)]
    #[ORM\JoinColumn(name: 'materia_id', nullable: false, onDelete: 'CASCADE')]
    public Materia $materia;

    #[ORM\ManyToOne(targetEntity: Grupo::class)]
    #[ORM\JoinColumn(name: 'grupo_id', nullable: false, onDelete: 'CASCADE')]
    public Grupo $grupo;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function assign(Inscripcion $inscripcion, Materia $materia, Grupo $grupo): void
    {
        $this->inscripcion = $inscripcion;
        $this->materia = $materia;
        $this->grupo = $grupo;
    }
}
