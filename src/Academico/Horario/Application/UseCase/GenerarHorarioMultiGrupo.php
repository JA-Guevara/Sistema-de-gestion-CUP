<?php

declare(strict_types=1);

namespace App\Academico\Horario\Application\UseCase;

use App\Academico\Aula\Domain\Entity\Aula;
use App\Academico\Aula\Infrastructure\Persistence\AulaRepository;
use App\Academico\Grupo\Domain\Entity\Grupo;
use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;
use App\Academico\Horario\Application\DTO\HorarioMultiGrupoInput;
use App\Academico\Horario\Domain\Entity\Horario;
use App\Academico\Horario\Domain\Exception\HorarioException;
use App\Academico\Horario\Infrastructure\Persistence\HorarioRepository;
use App\Academico\Materia\Domain\Entity\Materia;
use App\Academico\Materia\Infrastructure\Persistence\MateriaRepository;
use App\Academico\Turno\Domain\Entity\Turno;
use App\Academico\Turno\Infrastructure\Persistence\TurnoRepository;
use App\Bitacora\Application\EventLog\HorarioEvents;

/**
 * Genera el horario semanal de varios grupos en una sola operacion,
 * a partir de un turno del catalogo, con estrategia PARALELO o ESCALONADO.
 */
final readonly class GenerarHorarioMultiGrupo
{
    private const DIAS_VALIDOS = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'];
    private const DURACION_MAXIMA_MINUTOS = 600;

    public function __construct(
        private HorarioRepository $horarios,
        private TurnoRepository $turnos,
        private GrupoRepository $grupos,
        private MateriaRepository $materias,
        private AulaRepository $aulas,
        private HorarioEvents $events,
    ) {
    }

    /**
     * @return list<Horario>
     */
    public function execute(HorarioMultiGrupoInput $input): array
    {
        $turno = $this->loadTurno($input);
        $estrategia = $this->validateEstrategia($input);
        $dias = $this->validateDays($input->dias);
        $this->validateParameters($input);

        $materias = $this->loadMaterias($input->materiaIds);
        $gruposConAula = $this->loadGrupos($input->grupos);

        $this->validateEstrategiaCabe($estrategia, count($gruposConAula), count($materias));
        $this->validateFitsInTurno($turno, count($materias), $input);

        if ($input->reemplazar) {
            foreach ($gruposConAula as $par) {
                $this->horarios->deleteByGrupo($par['grupo']->id ?? 0);
            }
        }

        $horarios = $this->buildHorarios($turno, $estrategia, $dias, $input, $materias, $gruposConAula);
        $this->horarios->saveMany($horarios);
        $this->registerAudit($estrategia, $horarios, $gruposConAula, $input);

        return $horarios;
    }

    private function loadTurno(HorarioMultiGrupoInput $input): Turno
    {
        $turno = $this->turnos->findById($input->turnoId);
        if ($turno === null) {
            throw new HorarioException('El turno seleccionado no existe.');
        }

        return $turno;
    }

    private function validateEstrategia(HorarioMultiGrupoInput $input): string
    {
        $estrategia = mb_strtoupper(trim($input->estrategia));
        if (!in_array($estrategia, [HorarioMultiGrupoInput::ESTRATEGIA_PARALELO, HorarioMultiGrupoInput::ESTRATEGIA_ESCALONADO], true)) {
            throw new HorarioException('La estrategia de generacion no es valida.');
        }

        return $estrategia;
    }

    /**
     * @param list<string> $dias
     *
     * @return list<string>
     */
    private function validateDays(array $dias): array
    {
        $normalizados = array_values(array_unique(array_map(
            static fn (string $dia): string => mb_strtoupper(trim($dia)),
            $dias,
        )));

        if ($normalizados === []) {
            throw new HorarioException('Debe seleccionar al menos un dia.');
        }

        foreach ($normalizados as $dia) {
            if (!in_array($dia, self::DIAS_VALIDOS, true)) {
                throw new HorarioException('Uno de los dias seleccionados no es valido.');
            }
        }

        return $normalizados;
    }

    private function validateParameters(HorarioMultiGrupoInput $input): void
    {
        if ($input->duracionMinutos <= 0 || $input->duracionMinutos > self::DURACION_MAXIMA_MINUTOS) {
            throw new HorarioException('La duracion por clase debe estar entre 1 y 600 minutos.');
        }

        if ($input->descansoMinutos < 0 || $input->descansoMinutos > self::DURACION_MAXIMA_MINUTOS) {
            throw new HorarioException('El descanso entre clases no es valido.');
        }

        if ($input->materiaIds === []) {
            throw new HorarioException('Debe seleccionar al menos una materia.');
        }

        if ($input->grupos === []) {
            throw new HorarioException('Debe seleccionar al menos un grupo.');
        }
    }

    /**
     * @param list<int> $materiaIds
     *
     * @return list<Materia>
     */
    private function loadMaterias(array $materiaIds): array
    {
        $materias = [];
        $vistas = [];
        foreach ($materiaIds as $materiaId) {
            if (in_array($materiaId, $vistas, true)) {
                throw new HorarioException('Una materia fue seleccionada mas de una vez.');
            }
            $vistas[] = $materiaId;

            $materia = $this->materias->findById($materiaId);
            if ($materia === null || !$materia->isActive()) {
                throw new HorarioException('Una de las materias seleccionadas no existe o esta inactiva.');
            }

            $materias[] = $materia;
        }

        return $materias;
    }

    /**
     * @param list<array{grupoId:int, aulaId:int}> $grupos
     *
     * @return list<array{grupo:Grupo, aula:Aula}>
     */
    private function loadGrupos(array $grupos): array
    {
        $resueltos = [];
        $vistos = [];
        foreach ($grupos as $par) {
            if (in_array($par['grupoId'], $vistos, true)) {
                throw new HorarioException('Un grupo fue seleccionado mas de una vez.');
            }
            $vistos[] = $par['grupoId'];

            $grupo = $this->grupos->findById($par['grupoId']);
            if ($grupo === null) {
                throw new HorarioException('Uno de los grupos seleccionados no existe.');
            }

            $aula = $this->aulas->findById($par['aulaId']);
            if ($aula === null || !$aula->isActive()) {
                throw new HorarioException(sprintf('El aula asignada al grupo %s no existe o esta inactiva.', $grupo->codigo));
            }

            $resueltos[] = ['grupo' => $grupo, 'aula' => $aula];
        }

        return $resueltos;
    }

    private function validateEstrategiaCabe(string $estrategia, int $cantidadGrupos, int $cantidadMaterias): void
    {
        if ($estrategia === HorarioMultiGrupoInput::ESTRATEGIA_ESCALONADO && $cantidadGrupos > $cantidadMaterias) {
            throw new HorarioException(sprintf(
                'En modo escalonado no puede haber mas grupos (%d) que materias (%d): la rotacion provocaria choques.',
                $cantidadGrupos,
                $cantidadMaterias,
            ));
        }
    }

    private function validateFitsInTurno(Turno $turno, int $cantidadMaterias, HorarioMultiGrupoInput $input): void
    {
        $minutosNecesarios = ($cantidadMaterias * $input->duracionMinutos) + (max(0, $cantidadMaterias - 1) * $input->descansoMinutos);
        $finRequerido = $turno->horaInicio->modify(sprintf('+%d minutes', $minutosNecesarios));

        if ($finRequerido > $turno->horaFin) {
            throw new HorarioException(sprintf(
                'Las materias no entran en el turno: requieren hasta las %s pero el turno %s termina a las %s.',
                $finRequerido->format('H:i'),
                $turno->nombre,
                $turno->horaFin->format('H:i'),
            ));
        }
    }

    /**
     * @param list<string>                          $dias
     * @param list<Materia>                         $materias
     * @param list<array{grupo:Grupo, aula:Aula}>   $gruposConAula
     *
     * @return list<Horario>
     */
    private function buildHorarios(Turno $turno, string $estrategia, array $dias, HorarioMultiGrupoInput $input, array $materias, array $gruposConAula): array
    {
        $total = count($materias);
        $paso = $input->duracionMinutos + $input->descansoMinutos;
        $horarios = [];
        // Franjas (aula, dia, hora) ya agregadas en ESTE lote, para detectar
        // choques de aula entre grupos del mismo lote (hasOverlap solo ve la BD).
        $ocupadas = [];

        foreach ($gruposConAula as $indiceGrupo => $par) {
            $grupo = $par['grupo'];
            $aula = $par['aula'];

            for ($slot = 0; $slot < $total; $slot++) {
                $indiceMateria = $estrategia === HorarioMultiGrupoInput::ESTRATEGIA_ESCALONADO
                    ? ($slot + $indiceGrupo) % $total
                    : $slot;
                $materia = $materias[$indiceMateria];

                $horaInicio = $turno->horaInicio->modify(sprintf('+%d minutes', $slot * $paso));
                $horaFin = $horaInicio->modify(sprintf('+%d minutes', $input->duracionMinutos));

                foreach ($dias as $dia) {
                    if ($this->horarios->hasOverlap($grupo->id ?? 0, $aula->id ?? 0, $dia, $horaInicio, $horaFin)) {
                        throw new HorarioException(sprintf(
                            'Choque el dia %s a las %s-%s para el grupo %s o su aula %s.',
                            $dia,
                            $horaInicio->format('H:i'),
                            $horaFin->format('H:i'),
                            $grupo->codigo,
                            $aula->codigo,
                        ));
                    }

                    if ($this->solapaEnLote($ocupadas, $aula->id ?? 0, $dia, $horaInicio, $horaFin)) {
                        throw new HorarioException(sprintf(
                            'Choque de aula en el lote: el aula %s ya esta ocupada el %s a las %s-%s por otro grupo del mismo lote.',
                            $aula->codigo,
                            $dia,
                            $horaInicio->format('H:i'),
                            $horaFin->format('H:i'),
                        ));
                    }

                    $horario = new Horario();
                    $horario->configure($grupo, $materia, $aula, $dia, $horaInicio, $horaFin);
                    $horarios[] = $horario;
                    $ocupadas[] = ['aulaId' => $aula->id ?? 0, 'dia' => $dia, 'inicio' => $horaInicio, 'fin' => $horaFin];
                }
            }
        }

        return $horarios;
    }

    /**
     * @param list<Horario>                       $horarios
     * @param list<array{grupo:Grupo, aula:Aula}> $gruposConAula
     */
    private function registerAudit(string $estrategia, array $horarios, array $gruposConAula, HorarioMultiGrupoInput $input): void
    {
        $this->events->generadoMultiGrupo($estrategia, count($horarios), count($gruposConAula), $input->actorUserId);
    }

    /**
     * Choque de aula dentro del mismo lote: misma aula, mismo dia y rango horario
     * solapado (inicioA < finB AND inicioB < finA).
     *
     * @param list<array{aulaId:int, dia:string, inicio:\DateTimeImmutable, fin:\DateTimeImmutable}> $ocupadas
     */
    private function solapaEnLote(array $ocupadas, int $aulaId, string $dia, \DateTimeImmutable $inicio, \DateTimeImmutable $fin): bool
    {
        foreach ($ocupadas as $o) {
            if ($o['aulaId'] === $aulaId && $o['dia'] === $dia && $inicio < $o['fin'] && $o['inicio'] < $fin) {
                return true;
            }
        }

        return false;
    }
}
