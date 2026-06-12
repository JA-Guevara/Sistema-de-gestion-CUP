<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Bitacora\Application\EventLog\InscripcionEvents;
use App\Inscripcion\Application\DTO\InscripcionEditInput;
use App\Inscripcion\Domain\Entity\Inscripcion;
use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;

final readonly class ActualizarInscripcion
{
    public function __construct(
        private InscripcionRepository $inscripciones,
        private InscripcionEvents $events,
    ) {
    }

    public function execute(InscripcionEditInput $input): Inscripcion
    {
        $inscripcion = $this->inscripciones->findById($input->inscripcionId);
        if ($inscripcion === null) {
            throw new InscripcionException('Inscripcion no encontrada.');
        }

        $antes = [
            'nombres' => $inscripcion->nombres,
            'apellidos' => $inscripcion->apellidos,
            'email' => $inscripcion->email,
            'carrera' => $inscripcion->carrera->id,
        ];

        $inscripcion->nombres = trim($input->nombres);
        $inscripcion->apellidos = trim($input->apellidos);
        $inscripcion->email = mb_strtolower(trim($input->email));
        $inscripcion->direccion = $input->direccion;
        $inscripcion->telefono = $input->telefono;
        $inscripcion->colegioProcedencia = $input->colegioProcedencia;
        $inscripcion->ciudad = $input->ciudad;
        $inscripcion->tituloBachiller = $input->tituloBachiller;
        $inscripcion->turnoPreferencia = $input->turnoPreferencia;
        $inscripcion->otros = $input->otros;

        $this->inscripciones->flush();

        $despues = [
            'nombres' => $input->nombres,
            'apellidos' => $input->apellidos,
            'email' => $input->email,
            'carrera' => $input->carreraId,
        ];
        $this->events->editada($inscripcion->ci, $antes, $despues, $input->actorUserId);

        return $inscripcion;
    }
}
