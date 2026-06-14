<?php

declare(strict_types=1);

namespace App\Notas\Domain\Entity;

use App\Academico\Materia\Domain\Entity\Materia;
use App\Auth\Entity\User;
use App\Inscripcion\Domain\Entity\Inscripcion;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notas')]
#[ORM\UniqueConstraint(name: 'uniq_nota_inscripcion_materia_examen', columns: ['inscripcion_id', 'materia_id', 'numero_examen'])]
class Nota
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

    /** Número de examen/parcial (1..ConfiguracionGestion::cantidadExamenes). */
    #[ORM\Column]
    public int $numeroExamen;

    /** Calificación en escala 0..100. */
    #[ORM\Column]
    public int $valor;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $observacion = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'registrada_por_id', nullable: true, onDelete: 'SET NULL')]
    public ?User $registradaPor = null;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function setValor(int $valor, ?string $observacion, ?User $registradaPor): void
    {
        $this->valor = $valor;
        $this->observacion = $observacion !== null && trim($observacion) !== '' ? trim($observacion) : null;
        $this->registradaPor = $registradaPor;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
