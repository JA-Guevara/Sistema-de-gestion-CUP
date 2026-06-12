<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Academico\Carrera\Infrastructure\Persistence\CarreraRepository;
use App\Auth\Entity\User;
use App\Bitacora\Application\EventLog\InscripcionEvents;
use App\Gestion\Domain\Catalog\EstadoGestion;
use App\Gestion\Domain\Entity\CupoGestion;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Inscripcion\Application\DTO\InscripcionInput;
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
        $this->validateCarreraEnGestion($gestion, $input->carreraId);
        $this->validateCupoDisponible($gestion);
        $this->validateCiUnico($input->ci);
        $this->validateUserNotAlreadyInscribed($user, $gestion);
        $this->validateDatosObligatorios($input);

        $carrera = $this->carreras->findById($input->carreraId);

        $inscripcion = $this->createInscripcion($input, $user, $gestion, $carrera);
        $this->updateCupo($gestion);
        $this->inscripciones->save($inscripcion);
        $this->registerAudit($inscripcion, $input);

        return $inscripcion;
    }

    private function validateGestionActiva(?\App\Gestion\Domain\Entity\Gestion $gestion): void
    {
        if ($gestion === null) {
            throw new InscripcionException('No hay una gestion activa en este momento.');
        }
    }

    private function validateInscripcionAbierta(\App\Gestion\Domain\Entity\Gestion $gestion): void
    {
        if ($gestion->estado !== EstadoGestion::ABIERTA_INSCRIPCION) {
            throw new InscripcionException('Las inscripciones no estan abiertas en este momento.');
        }
    }

    private function validateCarreraEnGestion(\App\Gestion\Domain\Entity\Gestion $gestion, int $carreraId): void
    {
        $carreraEnGestion = null;
        foreach ($gestion->carreras as $cg) {
            if ($cg->carrera->id === $carreraId && $cg->habilitada) {
                $carreraEnGestion = $cg;
                break;
            }
        }

        if ($carreraEnGestion === null) {
            throw new InscripcionException('La carrera seleccionada no esta disponible en esta gestion.');
        }
    }

    private function validateCupoDisponible(\App\Gestion\Domain\Entity\Gestion $gestion): void
    {
        $cupo = $gestion->cupos->first();
        if (!$cupo instanceof CupoGestion || $cupo->disponibles <= 0) {
            throw new InscripcionException('No hay cupos disponibles en esta gestion.');
        }
    }

    private function validateCiUnico(string $ci): void
    {
        if ($this->inscripciones->findByCi($ci) !== null) {
            throw new InscripcionException('Ya existe una inscripcion con ese CI.');
        }
    }

    private function validateUserNotAlreadyInscribed(User $user, \App\Gestion\Domain\Entity\Gestion $gestion): void
    {
        if ($this->inscripciones->findByUserAndGestion($user->id, $gestion->id) !== null) {
            throw new InscripcionException('Ya tienes una inscripcion registrada en esta gestion.');
        }
    }

    private function validateDatosObligatorios(InscripcionInput $input): void
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

    private function createInscripcion(InscripcionInput $input, User $user, \App\Gestion\Domain\Entity\Gestion $gestion, \App\Academico\Carrera\Domain\Entity\Carrera $carrera): Inscripcion
    {
        $inscripcion = new Inscripcion();
        $inscripcion->user = $user;
        $inscripcion->gestion = $gestion;
        $inscripcion->carrera = $carrera;
        $inscripcion->ci = trim($input->ci);
        $inscripcion->nombres = trim($input->nombres);
        $inscripcion->apellidos = trim($input->apellidos);
        $inscripcion->fechaNacimiento = $input->fechaNacimiento;
        $inscripcion->sexo = $input->sexo;
        $inscripcion->direccion = $input->direccion;
        $inscripcion->telefono = $input->telefono;
        $inscripcion->email = mb_strtolower(trim($input->email));
        $inscripcion->colegioProcedencia = $input->colegioProcedencia;
        $inscripcion->ciudad = $input->ciudad;
        $inscripcion->tituloBachiller = $input->tituloBachiller;
        $inscripcion->turnoPreferencia = $input->turnoPreferencia;
        $inscripcion->otros = $input->otros;

        return $inscripcion;
    }

    private function updateCupo(\App\Gestion\Domain\Entity\Gestion $gestion): void
    {
        $cupo = $gestion->cupos->first();
        if ($cupo instanceof CupoGestion) {
            $cupo->inscritos++;
            $cupo->disponibles = max(0, $cupo->disponibles - 1);
        }
    }

    private function registerAudit(Inscripcion $inscripcion, InscripcionInput $input): void
    {
        $this->events->creada($inscripcion->ci, $inscripcion->nombres . ' ' . $inscripcion->apellidos, $input->actorUserId);
    }
}
