<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Academico\Carrera\Domain\Entity\Carrera;
use App\Academico\Carrera\Infrastructure\Persistence\CarreraRepository;
use App\Auth\Entity\User;
use App\Bitacora\Application\EventLog\InscripcionEvents;
use App\Gestion\Domain\Catalog\EstadoGestion;
use App\Gestion\Domain\Entity\CupoGestion;
use App\Gestion\Domain\Entity\Gestion;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Inscripcion\Application\DTO\InscripcionInput;
use App\Inscripcion\Domain\Catalog\EstadoInscripcion;
use App\Inscripcion\Domain\Catalog\TipoPostulacion;
use App\Inscripcion\Domain\Entity\Inscripcion;
use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;

final readonly class CrearInscripcion
{
    public function __construct(
        private InscripcionRepository $inscripciones,
        private GestionRepository $gestiones,
        private CarreraRepository $carreras,
        private InscripcionEvents $events,
    ) {
    }

    public function execute(InscripcionInput $input, User $user): Inscripcion
    {
        $gestion = $this->gestiones->findActive();
        $this->validateGestionActiva($gestion);
        $this->validateInscripcionAbierta($gestion);
        $this->validateDatosComunes($input);

        $esPresentar = $input->esPresentar();
        $carrera = null;
        $carreraSegunda = null;

        if ($input->tipo === TipoPostulacion::ESTUDIANTE) {
            if ($esPresentar) {
                $this->validateCarreraEnGestion($gestion, $input->carreraId);
                $this->validateCupoDisponible($gestion);
            }
            $carrera = $input->carreraId > 0 ? $this->carreras->findById($input->carreraId) : null;
            $carreraSegunda = $input->carreraSegundaId > 0 ? $this->carreras->findById($input->carreraSegundaId) : null;
        } elseif ($esPresentar) {
            $this->validateRequisitosDocente($input);
        }

        $inscripcion = $this->createInscripcion($input, $user, $gestion, $carrera, $carreraSegunda);
        $inscripcion->estado = $esPresentar ? EstadoInscripcion::PRESENTADA : EstadoInscripcion::BORRADOR;

        if ($input->tipo === TipoPostulacion::ESTUDIANTE && $esPresentar) {
            $this->updateCupo($gestion);
        }

        $this->inscripciones->save($inscripcion);
        $this->registerAudit($inscripcion, $input);

        return $inscripcion;
    }

    private function validateGestionActiva(?Gestion $gestion): void
    {
        if ($gestion === null) {
            throw new InscripcionException('No hay una gestion activa en este momento.');
        }
    }

    private function validateInscripcionAbierta(Gestion $gestion): void
    {
        if ($gestion->estado !== EstadoGestion::ABIERTA_INSCRIPCION) {
            throw new InscripcionException('Las inscripciones no estan abiertas en este momento.');
        }
    }

    private function validateCarreraEnGestion(Gestion $gestion, int $carreraId): void
    {
        foreach ($gestion->carreras as $cg) {
            if ($cg->carrera->id === $carreraId && $cg->habilitada) {
                return;
            }
        }

        throw new InscripcionException('La carrera seleccionada no esta disponible en esta gestion.');
    }

    private function validateCupoDisponible(Gestion $gestion): void
    {
        $cupo = $gestion->cupos->first();
        if (!$cupo instanceof CupoGestion || $cupo->disponibles <= 0) {
            throw new InscripcionException('No hay cupos disponibles en esta gestion.');
        }
    }

    private function validateDatosComunes(InscripcionInput $input): void
    {
        if (trim($input->ci) === '' || trim($input->nombres) === '' || trim($input->apellidos) === '') {
            throw new InscripcionException('CI, nombres y apellidos son obligatorios.');
        }

        if (trim($input->email) === '' || !filter_var($input->email, FILTER_VALIDATE_EMAIL)) {
            throw new InscripcionException('Correo electronico invalido.');
        }

        if (!in_array($input->sexo, ['M', 'F'], true)) {
            throw new InscripcionException('Sexo debe ser M o F.');
        }

        if ($input->fechaNacimiento > new \DateTimeImmutable()) {
            throw new InscripcionException('Fecha de nacimiento no puede ser futura.');
        }
    }

    private function validateRequisitosDocente(InscripcionInput $input): void
    {
        if ($input->docenteProfesion === null || trim($input->docenteProfesion) === '') {
            throw new InscripcionException('Para presentar como docente debes indicar tu profesion o area.');
        }
    }

    private function createInscripcion(InscripcionInput $input, User $user, Gestion $gestion, ?Carrera $carrera, ?Carrera $carreraSegunda): Inscripcion
    {
        $inscripcion = new Inscripcion();
        $inscripcion->user = $user;
        $inscripcion->gestion = $gestion;
        $inscripcion->tipo = $input->tipo;
        $inscripcion->modalidad = $input->modalidad;
        $inscripcion->ci = trim($input->ci);
        $inscripcion->nombres = trim($input->nombres);
        $inscripcion->apellidos = trim($input->apellidos);
        $inscripcion->fechaNacimiento = $input->fechaNacimiento;
        $inscripcion->sexo = $input->sexo;
        $inscripcion->direccion = $input->direccion;
        $inscripcion->telefono = $input->telefono;
        $inscripcion->email = mb_strtolower(trim($input->email));
        $inscripcion->ciudad = $input->ciudad;
        $inscripcion->carrera = $carrera;
        $inscripcion->carreraSegunda = $carreraSegunda;
        $inscripcion->colegioProcedencia = $input->colegioProcedencia;
        $inscripcion->tituloBachiller = $input->tituloBachiller;
        $inscripcion->turnoPreferencia = $input->turnoPreferencia;
        $inscripcion->docenteProfesion = $input->docenteProfesion;
        $inscripcion->docenteMaestria = $input->docenteMaestria;
        $inscripcion->docenteDiplomado = $input->docenteDiplomado;
        $inscripcion->docenteExperiencia = $input->docenteExperiencia;
        $inscripcion->otros = $input->otros;

        return $inscripcion;
    }

    private function updateCupo(Gestion $gestion): void
    {
        $cupo = $gestion->cupos->first();
        if ($cupo instanceof CupoGestion) {
            $cupo->inscritos++;
            $cupo->disponibles = max(0, $cupo->disponibles - 1);
        }
    }

    private function registerAudit(Inscripcion $inscripcion, InscripcionInput $input): void
    {
        $nombre = $inscripcion->nombres . ' ' . $inscripcion->apellidos;
        $this->events->creada($inscripcion->ci, sprintf('%s (%s)', $nombre, TipoPostulacion::label($inscripcion->tipo)), $input->actorUserId);
    }
}
