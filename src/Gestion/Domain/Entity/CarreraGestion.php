<?php

declare(strict_types=1);

namespace App\Gestion\Domain\Entity;

use App\Academico\Carrera\Domain\Entity\Carrera;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'gestion_carreras')]
#[ORM\UniqueConstraint(name: 'uniq_gestion_carrera', columns: ['gestion_id', 'carrera_id'])]
class CarreraGestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Gestion::class, inversedBy: 'carreras')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Gestion $gestion;

    #[ORM\ManyToOne(targetEntity: Carrera::class)]
    #[ORM\JoinColumn(name: 'carrera_id', referencedColumnName: 'id', nullable: false)]
    public Carrera $carrera;

    #[ORM\Column(options: ['default' => true])]
    public bool $habilitada = true;

    #[ORM\Column]
    public int $cupoCarrera;

    public static function create(Carrera $catalogCarrera, bool $habilitada, int $cupoCarrera): self
    {
        $carrera = new self();
        $carrera->carrera = $catalogCarrera;
        $carrera->habilitada = $habilitada;
        $carrera->cupoCarrera = $cupoCarrera;

        return $carrera;
    }
}
