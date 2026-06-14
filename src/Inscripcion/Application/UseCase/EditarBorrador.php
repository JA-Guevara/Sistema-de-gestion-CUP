<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Academico\Carrera\Infrastructure\Persistence\CarreraRepository;
use App\Bitacora\Application\EventLog\InscripcionEvents;
use App\Inscripcion\Application\DTO\InscripcionInput;
use App\Inscripcion\Domain\Entity\Inscripcion;
use App\Inscripcion\Domain\Catalog\TipoPostulacion;
use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;

/**
 * Edicion completa de un BORRADOR por su dueno (todos los campos). Puede
 * guardarse de nuevo como borrador o presentarse (BORRADOR -> PRESENTADA).
 * Una postulacion ya presentada NO se edita.
 */
final readonly class EditarBorrador
{
    public function __construct(
        private InscripcionRepository $inscripciones,
        private CarreraRepository $carreras,
        private InscripcionEvents $events,
    ) {
    }

    public function execute(int $inscripcionId, InscripcionInput $input, int $actorUserId): Inscripcion
    {
        $inscripcion = $this->inscripciones->findById($inscripcionId);
        if ($inscripcion === null) {
            throw new InscripcionException('La postulacion no existe.');
        }

        if ($inscripcion->user->id !== $actorUserId) {
            throw new InscripcionException('No puedes editar una postulacion que no es tuya.');
        }

        if (!$inscripcion->puedeEditarse()) {
            throw new InscripcionException('Solo puedes editar un borrador; una postulacion presentada ya no se edita.');
        }

        $this->validateDatosComunes($input);

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
        $inscripcion->otros = $input->otros;

        if ($input->tipo === TipoPostulacion::ESTUDIANTE) {
            $inscripcion->carrera = $input->carreraId > 0 ? $this->carreras->findById($input->carreraId) : null;
            $inscripcion->carreraSegunda = $input->carreraSegundaId > 0 ? $this->carreras->findById($input->carreraSegundaId) : null;
            $inscripcion->colegioProcedencia = $input->colegioProcedencia;
            $inscripcion->tituloBachiller = $input->tituloBachiller;
            $inscripcion->turnoPreferencia = $input->turnoPreferencia;
            $inscripcion->docenteProfesion = null;
            $inscripcion->docenteMaestria = false;
            $inscripcion->docenteDiplomado = false;
            $inscripcion->docenteExperiencia = null;
        } else {
            $inscripcion->docenteProfesion = $input->docenteProfesion;
            $inscripcion->docenteMaestria = $input->docenteMaestria;
            $inscripcion->docenteDiplomado = $input->docenteDiplomado;
            $inscripcion->docenteExperiencia = $input->docenteExperiencia;
            $inscripcion->carrera = null;
            $inscripcion->carreraSegunda = null;
            $inscripcion->colegioProcedencia = null;
            $inscripcion->tituloBachiller = false;
            $inscripcion->turnoPreferencia = null;
        }

        $esPresentar = $input->esPresentar();
        if ($esPresentar) {
            if ($input->tipo === TipoPostulacion::DOCENTE && ($input->docenteProfesion === null || trim($input->docenteProfesion) === '')) {
                throw new InscripcionException('Para presentar como docente debes indicar tu profesion o area.');
            }
            if ($input->tipo === TipoPostulacion::ESTUDIANTE && $inscripcion->carrera === null) {
                throw new InscripcionException('Selecciona una carrera para presentar como estudiante.');
            }
            $inscripcion->presentar();
        }

        $this->inscripciones->flush();

        $this->events->editada($inscripcion->ci, [], [], $actorUserId);
        if ($esPresentar) {
            $this->events->presentada($inscripcion->ci, $actorUserId);
        }

        return $inscripcion;
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
}
