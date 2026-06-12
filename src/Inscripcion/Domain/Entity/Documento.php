<?php

declare(strict_types=1);

namespace App\Inscripcion\Domain\Entity;

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

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
