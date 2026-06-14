<?php

declare(strict_types=1);

namespace App\Notas\Domain\Entity;

use App\Academico\Grupo\Domain\Entity\Grupo;
use App\Academico\Materia\Domain\Entity\Materia;
use App\Auth\Entity\User;
use App\Gestion\Domain\Entity\Gestion;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notas_docente_materia')]
#[ORM\UniqueConstraint(name: 'uniq_docente_materia_grupo', columns: ['gestion_id', 'materia_id', 'grupo_id'])]
class AsignacionDocente
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Gestion::class)]
    #[ORM\JoinColumn(name: 'gestion_id', nullable: false, onDelete: 'CASCADE')]
    public Gestion $gestion;

    #[ORM\ManyToOne(targetEntity: Materia::class)]
    #[ORM\JoinColumn(name: 'materia_id', nullable: false, onDelete: 'CASCADE')]
    public Materia $materia;

    #[ORM\ManyToOne(targetEntity: Grupo::class)]
    #[ORM\JoinColumn(name: 'grupo_id', nullable: false, onDelete: 'CASCADE')]
    public Grupo $grupo;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'docente_id', nullable: false, onDelete: 'CASCADE')]
    public User $docente;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function assign(Gestion $gestion, Materia $materia, Grupo $grupo, User $docente): void
    {
        $this->gestion = $gestion;
        $this->materia = $materia;
        $this->grupo = $grupo;
        $this->docente = $docente;
    }
}
