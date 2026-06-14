<?php

declare(strict_types=1);

namespace App\Inscripcion\Domain\Entity;

use App\Academico\Carrera\Domain\Entity\Carrera;
use App\Auth\Entity\User;
use App\Gestion\Domain\Entity\Gestion;
use App\Inscripcion\Domain\Catalog\EstadoInscripcion;
use App\Inscripcion\Domain\Catalog\EstadoVerificacion;
use App\Inscripcion\Domain\Catalog\ModalidadPostulacion;
use App\Inscripcion\Domain\Catalog\RequisitoCatalog;
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

    /**
     * Acta de control de recepcion: estado de cada requisito el dia de la cita.
     *
     * @var \Doctrine\Common\Collections\Collection<int, \App\Inscripcion\Domain\Entity\VerificacionDocumento>
     */
    #[ORM\OneToMany(mappedBy: 'inscripcion', targetEntity: VerificacionDocumento::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public \Doctrine\Common\Collections\Collection $verificaciones;

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

    /** El postulante solicito anular su postulacion (validada/confirmada). */
    #[ORM\Column(options: ['default' => false])]
    public bool $anulacionSolicitada = false;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $motivoAnulacion = null;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->documentos = new ArrayCollection();
        $this->verificaciones = new ArrayCollection();
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

    public function agendarEntrevista(\DateTimeImmutable $fecha): void
    {
        $this->fechaEntrevista = $fecha;
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

    public function isAnulada(): bool
    {
        return $this->estado === EstadoInscripcion::ANULADA;
    }

    /** Solo un borrador puede editarse libremente; una vez presentada, no. */
    public function puedeEditarse(): bool
    {
        return $this->estado === EstadoInscripcion::BORRADOR;
    }

    /** Estado terminal: ya no admite acciones. */
    public function esTerminal(): bool
    {
        return in_array($this->estado, [EstadoInscripcion::RECHAZADA, EstadoInscripcion::ANULADA], true);
    }

    public function solicitarAnulacion(?string $motivo): void
    {
        $this->anulacionSolicitada = true;
        $this->motivoAnulacion = $motivo !== null && trim($motivo) !== '' ? trim($motivo) : null;
    }

    public function rechazarSolicitudAnulacion(): void
    {
        $this->anulacionSolicitada = false;
    }

    public function anular(): void
    {
        $this->estado = EstadoInscripcion::ANULADA;
        $this->anulacionSolicitada = false;
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

    /**
     * Verificacion del acta de recepcion para un requisito (null si aun no se
     * registro). Sirve para precargar el formulario del acta.
     */
    public function verificacionPorCodigo(string $codigo): ?VerificacionDocumento
    {
        foreach ($this->verificaciones as $verificacion) {
            if ($verificacion->requisitoCodigo === $codigo) {
                return $verificacion;
            }
        }

        return null;
    }

    /**
     * Resumen del acta de control de recepcion segun los requisitos del tipo.
     *
     * @return array{obligatorios:int, conformes:int, observados:int, pendientes:int}
     */
    public function resumenActa(): array
    {
        $estados = [];
        foreach ($this->verificaciones as $verificacion) {
            $estados[$verificacion->requisitoCodigo] = $verificacion->estado;
        }

        $obligatorios = 0;
        $conformes = 0;
        $observados = 0;
        $pendientes = 0;
        foreach (RequisitoCatalog::paraTipo($this->tipo) as $requisito) {
            $estado = $estados[$requisito['codigo']] ?? EstadoVerificacion::PENDIENTE;
            if ($estado === EstadoVerificacion::OBSERVADO) {
                $observados++;
            }
            if ($requisito['obligatorio']) {
                $obligatorios++;
                if ($estado === EstadoVerificacion::ENTREGADO) {
                    $conformes++;
                } else {
                    $pendientes++;
                }
            }
        }

        return [
            'obligatorios' => $obligatorios,
            'conformes' => $conformes,
            'observados' => $observados,
            'pendientes' => $pendientes,
        ];
    }

    /**
     * El acta esta conforme (gate de aprobacion) cuando TODOS los requisitos
     * obligatorios estan Entregados. Los opcionales no bloquean.
     */
    public function actaConforme(): bool
    {
        $resumen = $this->resumenActa();

        return $resumen['obligatorios'] > 0 && $resumen['conformes'] === $resumen['obligatorios'];
    }
}
