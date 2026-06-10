<?php

declare(strict_types=1);

namespace App\Academico\Grupo\Domain\Entity;

use App\Gestion\Domain\Entity\Gestion;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'grupos')]
#[ORM\UniqueConstraint(name: 'uniq_grupo_gestion_codigo', columns: ['gestion_id', 'codigo'])]
class Grupo
{
    public const ESTADO_ABIERTO = 'ABIERTO';
    public const ESTADO_CERRADO = 'CERRADO';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Gestion::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Gestion $gestion;

    #[ORM\Column(length: 30)]
    public string $codigo;

    #[ORM\Column(length: 120)]
    public string $nombre;

    #[ORM\Column]
    public int $cupo;

    #[ORM\Column(options: ['default' => 0])]
    public int $inscritosEstimados = 0;

    #[ORM\Column(length: 40)]
    public string $estado = self::ESTADO_ABIERTO;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function configure(Gestion $gestion, string $codigo, string $nombre, int $cupo, int $inscritosEstimados): void
    {
        $this->gestion = $gestion;
        $this->codigo = mb_strtoupper(trim($codigo));
        $this->nombre = trim($nombre);
        $this->cupo = $cupo;
        $this->inscritosEstimados = max(0, $inscritosEstimados);
        $this->touch();
    }

    public function close(): void
    {
        $this->estado = self::ESTADO_CERRADO;
        $this->touch();
    }

    public function open(): void
    {
        $this->estado = self::ESTADO_ABIERTO;
        $this->touch();
    }

    public function isOpen(): bool
    {
        return $this->estado === self::ESTADO_ABIERTO;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
