<?php

declare(strict_types=1);

namespace App\Academico\Horario\Domain\Entity;

use App\Academico\Aula\Domain\Entity\Aula;
use App\Academico\Grupo\Domain\Entity\Grupo;
use App\Academico\Materia\Domain\Entity\Materia;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'horarios')]
class Horario
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Grupo::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Grupo $grupo;

    #[ORM\ManyToOne(targetEntity: Materia::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Materia $materia;

    #[ORM\ManyToOne(targetEntity: Aula::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Aula $aula;

    #[ORM\Column(length: 20)]
    public string $dia;

    #[ORM\Column(type: 'time_immutable')]
    public \DateTimeImmutable $horaInicio;

    #[ORM\Column(type: 'time_immutable')]
    public \DateTimeImmutable $horaFin;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function configure(Grupo $grupo, Materia $materia, Aula $aula, string $dia, \DateTimeImmutable $horaInicio, \DateTimeImmutable $horaFin): void
    {
        $this->grupo = $grupo;
        $this->materia = $materia;
        $this->aula = $aula;
        $this->dia = mb_strtoupper(trim($dia));
        $this->horaInicio = $horaInicio;
        $this->horaFin = $horaFin;
    }
}
