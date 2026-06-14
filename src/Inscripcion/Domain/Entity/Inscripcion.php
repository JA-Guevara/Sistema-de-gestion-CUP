<?php

declare(strict_types=1);

namespace App\Inscripcion\Domain\Entity;

use App\Academico\Carrera\Domain\Entity\Carrera;
use App\Auth\Entity\User;
use App\Gestion\Domain\Entity\Gestion;
use App\Inscripcion\Domain\Catalog\EstadoInscripcion;
use App\Inscripcion\Domain\Catalog\ModalidadPostulacion;
use App\Inscripcion\Domain\Catalog\TipoPostulacion;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'inscripciones')]
#[ORM\Index(name: 'idx_inscripciones_user_gestion', columns: ['user_id', 'gestion_id'])]
#[ORM\Index(name: 'idx_inscripciones_ci', columns: ['ci'])]
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

    /** ESTUDIANTE | DOCENTE */
    #[ORM\Column(length: 20)]
    public string $tipo = TipoPostulacion::ESTUDIANTE;

    /** PRESENCIAL | VIRTUAL */
    #[ORM\Column(length: 20)]
    public string $modalidad = ModalidadPostulacion::PRESENCIAL;

    // ---- Datos comunes (estudiante y docente) ----

    #[ORM\Column(length: 20)]
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

    #[ORM\Column(length: 80, nullable: true)]
    public ?string $ciudad = null;

    // ---- Datos de estudiante ----

    #[ORM\ManyToOne(targetEntity: Carrera::class)]
    #[ORM\JoinColumn(name: 'carrera_id', nullable: true)]
    public ?Carrera $carrera = null;

    /** Segunda opción de carrera (si se llenan los cupos de la primera). */
    #[ORM\ManyToOne(targetEntity: Carrera::class)]
    #[ORM\JoinColumn(name: 'carrera_segunda_id', nullable: true)]
    public ?Carrera $carreraSegunda = null;

    #[ORM\Column(length: 120, nullable: true)]
    public ?string $colegioProcedencia = null;

    #[ORM\Column(options: ['default' => false])]
    public bool $tituloBachiller = false;

    #[ORM\Column(length: 20, nullable: true)]
    public ?string $turnoPreferencia = null;

    // ---- Datos de docente (requisitos: profesional en el área, maestría, diplomado) ----

    #[ORM\Column(length: 160, nullable: true)]
    public ?string $docenteProfesion = null;

    #[ORM\Column(options: ['default' => false])]
    public bool $docenteMaestria = false;

    #[ORM\Column(options: ['default' => false])]
    public bool $docenteDiplomado = false;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $docenteExperiencia = null;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $otros = null;

    /** @var \Doctrine\Common\Collections\Collection<int, \App\Inscripcion\Domain\Entity\Documento> */
    #[ORM\OneToMany(mappedBy: 'inscripcion', targetEntity: Documento::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public \Doctrine\Common\Collections\Collection $documentos;

    #[ORM\Column(length: 20)]
    public string $estado = EstadoInscripcion::BORRADOR;

    /** Fecha y hora agendada para la presentación de documentos. */
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $fechaPresentacionDocs = null;

    /** Fecha y hora agendada para la entrevista (docente). */
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $fechaEntrevista = null;

    /** Id del usuario que valido la documentación. */
    #[ORM\Column(nullable: true)]
    public ?int $validadaPor = null;

    /** Id del usuario que confirmo (admitio/contrato). */
    #[ORM\Column(nullable: true)]
    public ?int $confirmadaPor = null;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $motivoRechazo = null;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->documentos = new ArrayCollection();
    }

    public function presentar(): void
    {
        $this->estado = EstadoInscripcion::PRESENTADA;
    }

    public function completar(): void
    {
        $this->estado = EstadoInscripcion::COMPLETADA;
    }

    public function isBorrador(): bool
    {
        return $this->estado === EstadoInscripcion::BORRADOR;
    }

    public function isPendiente(): bool
    {
        return $this->estado === EstadoInscripcion::PENDIENTE;
    }

    public function esEstudiante(): bool
    {
        return $this->tipo === TipoPostulacion::ESTUDIANTE;
    }

    public function esDocente(): bool
    {
        return $this->tipo === TipoPostulacion::DOCENTE;
    }

    public function agendarRevision(\DateTimeImmutable $fecha): void
    {
        $this->fechaPresentacionDocs = $fecha;
    }

    public function validar(?int $actorUserId): void
    {
        $this->estado = EstadoInscripcion::VALIDADA;
        $this->validadaPor = $actorUserId;
    }

    public function rechazar(?string $motivo, ?int $actorUserId): void
    {
        $this->estado = EstadoInscripcion::RECHAZADA;
        $this->motivoRechazo = $motivo;
        $this->validadaPor = $actorUserId;
    }

    public function confirmar(?int $actorUserId): void
    {
        $this->estado = EstadoInscripcion::CONFIRMADA;
        $this->confirmadaPor = $actorUserId;
    }

    public function isPresentada(): bool
    {
        return $this->estado === EstadoInscripcion::PRESENTADA;
    }

    public function isValidada(): bool
    {
        return $this->estado === EstadoInscripcion::VALIDADA;
    }

    public function isConfirmada(): bool
    {
        return $this->estado === EstadoInscripcion::CONFIRMADA;
    }

    public function isRechazada(): bool
    {
        return $this->estado === EstadoInscripcion::RECHAZADA;
    }

    /**
     * Cuenta de documentos por estado. Util para el checklist de admision.
     *
     * @return array{total:int, aprobados:int, observados:int, pendientes:int}
     */
    public function resumenDocumentos(): array
    {
        $aprobados = 0;
        $observados = 0;
        $pendientes = 0;
        foreach ($this->documentos as $documento) {
            if ($documento->isAprobado()) {
                $aprobados++;
            } elseif ($documento->isObservado()) {
                $observados++;
            } else {
                $pendientes++;
            }
        }

        return [
            'total' => $aprobados + $observados + $pendientes,
            'aprobados' => $aprobados,
            'observados' => $observados,
            'pendientes' => $pendientes,
        ];
    }

    /**
     * La postulacion puede aprobarse (validarse) solo si tiene al menos un
     * documento y TODOS estan aprobados. Es el gate del checklist de admision
     * que habilita el pago/entrevista.
     */
    public function todosAprobados(): bool
    {
        if ($this->documentos->isEmpty()) {
            return false;
        }

        foreach ($this->documentos as $documento) {
            if (!$documento->isAprobado()) {
                return false;
            }
        }

        return true;
    }
}
