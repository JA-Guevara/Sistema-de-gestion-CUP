<?php

declare(strict_types=1);

namespace App\Gestion\Domain\Entity;

use App\Gestion\Domain\Catalog\EstadoGestion;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'gestiones')]
#[ORM\Index(name: 'idx_gestiones_estado', columns: ['estado'])]
class Gestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 40, unique: true)]
    public string $codigo;

    #[ORM\Column(length: 140)]
    public string $nombre;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $descripcion = null;

    #[ORM\Column(length: 40)]
    public string $estado = EstadoGestion::BORRADOR;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $fechaActivacion = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $fechaFinalizacion = null;

    #[ORM\Column(nullable: true)]
    public ?int $creadoPor = null;

    #[ORM\Column(nullable: true)]
    public ?int $activadoPor = null;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToOne(mappedBy: 'gestion', targetEntity: ConfiguracionGestion::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public ?ConfiguracionGestion $configuracion = null;

    /** @var Collection<int, PeriodoGestion> */
    #[ORM\OneToMany(mappedBy: 'gestion', targetEntity: PeriodoGestion::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $periodos;

    /** @var Collection<int, CarreraGestion> */
    #[ORM\OneToMany(mappedBy: 'gestion', targetEntity: CarreraGestion::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $carreras;

    /** @var Collection<int, CupoGestion> */
    #[ORM\OneToMany(mappedBy: 'gestion', targetEntity: CupoGestion::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $cupos;

    /** @var Collection<int, HistorialGestion> */
    #[ORM\OneToMany(mappedBy: 'gestion', targetEntity: HistorialGestion::class, cascade: ['persist'], orphanRemoval: true)]
    public Collection $historial;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->periodos = new ArrayCollection();
        $this->carreras = new ArrayCollection();
        $this->cupos = new ArrayCollection();
        $this->historial = new ArrayCollection();
    }

    public function rename(string $codigo, string $nombre, ?string $descripcion): void
    {
        $this->codigo = mb_strtoupper(trim($codigo));
        $this->nombre = trim($nombre);
        $this->descripcion = $descripcion !== null && trim($descripcion) !== '' ? trim($descripcion) : null;
        $this->touch();
    }

    public function configure(ConfiguracionGestion $configuracion): void
    {
        $this->configuracion = $configuracion;
        $configuracion->gestion = $this;
        $this->touch();
    }

    /** @param list<PeriodoGestion> $periodos */
    public function replacePeriods(array $periodos): void
    {
        $this->periodos->clear();
        foreach ($periodos as $periodo) {
            $periodo->gestion = $this;
            $this->periodos->add($periodo);
        }
        $this->touch();
    }

    /** @param list<CarreraGestion> $carreras */
    public function replaceCareers(array $carreras): void
    {
        $this->carreras->clear();
        foreach ($carreras as $carrera) {
            $carrera->gestion = $this;
            $this->carreras->add($carrera);
        }
        $this->touch();
    }

    public function replaceGlobalQuota(CupoGestion $cupo): void
    {
        $this->cupos->clear();
        $cupo->gestion = $this;
        $this->cupos->add($cupo);
        $this->touch();
    }

    public function activate(int $actorUserId): void
    {
        $now = new \DateTimeImmutable();
        $this->estado = EstadoGestion::CONFIGURACION;
        $this->fechaActivacion = $now;
        $this->fechaFinalizacion = null;
        $this->activadoPor = $actorUserId;
        $this->updatedAt = $now;
    }

    public function replaceAsInactive(): void
    {
        $this->estado = EstadoGestion::FINALIZADA;
        $this->fechaFinalizacion = new \DateTimeImmutable();
        $this->touch();
    }

    public function isActive(): bool
    {
        return $this->fechaActivacion !== null
            && $this->fechaFinalizacion === null
            && !in_array($this->estado, [EstadoGestion::FINALIZADA, EstadoGestion::CANCELADA], true);
    }

    public function openInscription(): void
    {
        $this->estado = EstadoGestion::ABIERTA_INSCRIPCION;
        $this->touch();
    }

    public function closeInscription(): void
    {
        $this->estado = EstadoGestion::VALIDACION_DOCUMENTAL;
        $this->touch();
    }

    public function addHistory(HistorialGestion $historial): void
    {
        $historial->gestion = $this;
        $this->historial->add($historial);
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
