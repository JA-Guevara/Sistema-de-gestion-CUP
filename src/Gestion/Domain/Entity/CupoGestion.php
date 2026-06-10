<?php

declare(strict_types=1);

namespace App\Gestion\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'gestion_cupos')]
class CupoGestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Gestion::class, inversedBy: 'cupos')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Gestion $gestion;

    #[ORM\Column]
    public int $cupoTotal;

    #[ORM\Column(options: ['default' => 0])]
    public int $inscritos = 0;

    #[ORM\Column(options: ['default' => 0])]
    public int $reservados = 0;

    #[ORM\Column]
    public int $disponibles;

    public static function create(int $cupoTotal): self
    {
        $cupo = new self();
        $cupo->cupoTotal = $cupoTotal;
        $cupo->disponibles = $cupoTotal;

        return $cupo;
    }
}
