<?php

declare(strict_types=1);

namespace App\Inscripcion\Domain\Entity;

use App\Academico\Carrera\Domain\Entity\Carrera;
use App\Auth\Entity\User;
use App\Gestion\Domain\Entity\Gestion;
use App\Inscripcion\Domain\Catalog\EstadoInscripcion;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'inscripciones')]
#[ORM\UniqueConstraint(name: 'uniq_inscripciones_user_gestion', columns: ['user_id', 'gestion_id'])]
#[ORM\UniqueConstraint(name: 'uniq_inscripciones_ci', columns: ['ci'])]
class Inscripcion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public User $user;

    #[ORM\ManyToOne(targetEntity: Gestion::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Gestion $gestion;

    #[ORM\ManyToOne(targetEntity: Carrera::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Carrera $carrera;

    #[ORM\Column(length: 20, unique: true)]
    public string $ci;

    #[ORM\Column(length: 120)]
    public string $nombres;

    #[ORM\Column(length: 120)]
    public string $apellidos;

    #[ORM\Column]
    public \DateTimeImmutable $fechaNacimiento;

    #[ORM\Column(length: 1)]
    public string $sexo;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $direccion = null;

    #[ORM\Column(length: 20, nullable: true)]
    public ?string $telefono = null;

    #[ORM\Column(length: 180)]
    public string $email;

    #[ORM\Column(length: 120, nullable: true)]
    public ?string $colegioProcedencia = null;

    #[ORM\Column(length: 80, nullable: true)]
    public ?string $ciudad = null;

    #[ORM\Column(options: ['default' => false])]
    public bool $tituloBachiller = false;

    #[ORM\Column(length: 20, nullable: true)]
    public ?string $turnoPreferencia = null;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $otros = null;

    /** @var \Doctrine\Common\Collections\Collection<int, \App\Inscripcion\Domain\Entity\Documento> */
    #[ORM\OneToMany(mappedBy: 'inscripcion', targetEntity: Documento::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public \Doctrine\Common\Collections\Collection $documentos;

    #[ORM\Column(length: 20)]
    public string $estado = EstadoInscripcion::PENDIENTE;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->documentos = new ArrayCollection();
    }

    public function completar(): void
    {
        $this->estado = EstadoInscripcion::COMPLETADA;
    }

    public function isPendiente(): bool
    {
        return $this->estado === EstadoInscripcion::PENDIENTE;
    }
}
