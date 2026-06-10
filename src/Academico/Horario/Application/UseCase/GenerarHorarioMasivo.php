<?php

declare(strict_types=1);

namespace App\Academico\Horario\Application\UseCase;

use App\Academico\Aula\Domain\Entity\Aula;
use App\Academico\Aula\Infrastructure\Persistence\AulaRepository;
use App\Academico\Grupo\Domain\Entity\Grupo;
use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;
use App\Academico\Horario\Application\DTO\HorarioMasivoInput;
use App\Academico\Horario\Domain\Entity\Horario;
use App\Academico\Horario\Domain\Exception\HorarioException;
use App\Academico\Horario\Infrastructure\Persistence\HorarioRepository;
use App\Academico\Materia\Domain\Entity\Materia;
use App\Academico\Materia\Infrastructure\Persistence\MateriaRepository;
use App\Bitacora\Application\EventLog\HorarioEvents;

/**
 * Genera, en una sola operacion, todo el horario semanal de un grupo:
 * reparte un rango horario en bloques consecutivos (uno por materia) y
 * crea un registro Horario por cada combinacion bloque x dia.
 */
final readonly class GenerarHorarioMasivo
{
    private const DIAS_VALIDOS = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'];
    private const DURACION_MAXIMA_MINUTOS = 600;

    public function __construct(
        private HorarioRepository $horarios,
        private GrupoRepository $grupos,
        private MateriaRepository $materias,
        private AulaRepository $aulas,
        private HorarioEvents $events,
    ) {
    }

    /**
     * @return list<Horario>
     */
    public function execute(HorarioMasivoInput $input): array
    {
        $grupo = $this->loadGrupo($input);
        $dias = $this->validateDays($input->dias);
        $this->validateRangeParameters($input);
        $bloques = $this->resolveBloques($input);
        $franjas = $this->buildFranjas($input, $bloques);
        $this->validateFitsInTurno($input, $franjas);

        if ($input->reemplazar) {
            $this->horarios->deleteByGrupo($grupo->id ?? 0);
        }

        $this->guardAgainstOverlaps($grupo, $dias, $franjas);
        $horarios = $this->materialize($grupo, $dias, $franjas);
        $this->horarios->saveMany($horarios);
        $this->registerAudit($grupo, $horarios, $input);

        return $horarios;
    }

    private function loadGrupo(HorarioMasivoInput $input): Grupo
    {
        $grupo = $this->grupos->findById($input->grupoId);
        if ($grupo === null) {
            throw new HorarioException('El grupo seleccionado no existe.');
        }

        return $grupo;
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

    private function validateRangeParameters(HorarioMasivoInput $input): void
    {
        if ($input->horaInicio === null) {
            throw new HorarioException('La hora de inicio es obligatoria.');
        }

        if ($input->duracionMinutos <= 0 || $input->duracionMinutos > self::DURACION_MAXIMA_MINUTOS) {
            throw new HorarioException('La duracion por materia debe estar entre 1 y 600 minutos.');
        }

        if ($input->descansoMinutos < 0 || $input->descansoMinutos > self::DURACION_MAXIMA_MINUTOS) {
            throw new HorarioException('El descanso entre bloques no es valido.');
        }

        if ($input->bloques === []) {
            throw new HorarioException('Debe seleccionar al menos una materia para generar el horario.');
        }
    }

    /**
     * Carga y ordena las materias/aulas seleccionadas, validando estado y duplicados.
     *
     * @return list<array{materia:Materia, aula:Aula}>
     */
    private function resolveBloques(HorarioMasivoInput $input): array
    {
        $bloques = $input->bloques;
        usort($bloques, static fn (array $a, array $b): int => $a['orden'] <=> $b['orden']);

        $resueltos = [];
        $materiasVistas = [];
        foreach ($bloques as $bloque) {
            if (in_array($bloque['materiaId'], $materiasVistas, true)) {
                throw new HorarioException('Una materia fue seleccionada mas de una vez.');
            }
            $materiasVistas[] = $bloque['materiaId'];

            $materia = $this->materias->findById($bloque['materiaId']);
            if ($materia === null || !$materia->isActive()) {
                throw new HorarioException('Una de las materias seleccionadas no existe o esta inactiva.');
            }

            $aula = $this->aulas->findById($bloque['aulaId']);
            if ($aula === null || !$aula->isActive()) {
                throw new HorarioException(sprintf('El aula asignada a la materia %s no existe o esta inactiva.', $materia->nombre));
            }

            $resueltos[] = ['materia' => $materia, 'aula' => $aula];
        }

        return $resueltos;
    }

    /**
     * Calcula la franja horaria consecutiva de cada bloque.
     *
     * @param list<array{materia:Materia, aula:Aula}> $bloques
     *
     * @return list<array{materia:Materia, aula:Aula, horaInicio:\DateTimeImmutable, horaFin:\DateTimeImmutable}>
     */
    private function buildFranjas(HorarioMasivoInput $input, array $bloques): array
    {
        /** @var \DateTimeImmutable $cursor */
        $cursor = $input->horaInicio;
        $franjas = [];

        foreach ($bloques as $bloque) {
            $horaInicio = $cursor;
            $horaFin = $cursor->modify(sprintf('+%d minutes', $input->duracionMinutos));

            $franjas[] = [
                'materia' => $bloque['materia'],
                'aula' => $bloque['aula'],
                'horaInicio' => $horaInicio,
                'horaFin' => $horaFin,
            ];

            $cursor = $horaFin->modify(sprintf('+%d minutes', $input->descansoMinutos));
        }

        return $franjas;
    }

    /**
     * Verifica que la ultima franja no exceda la hora de fin del turno.
     *
     * @param list<array{materia:Materia, aula:Aula, horaInicio:\DateTimeImmutable, horaFin:\DateTimeImmutable}> $franjas
     */
    private function validateFitsInTurno(HorarioMasivoInput $input, array $franjas): void
    {
        if ($input->horaFin === null || $franjas === []) {
            return;
        }

        $ultima = $franjas[count($franjas) - 1];
        if ($ultima['horaFin'] > $input->horaFin) {
            throw new HorarioException(sprintf(
                'Las materias no entran en el turno: requieren hasta las %s pero el turno termina a las %s. Reduci la duracion, quita materias o extiende el turno.',
                $ultima['horaFin']->format('H:i'),
                $input->horaFin->format('H:i'),
            ));
        }
    }

    /**
     * @param list<string>                                                                                  $dias
     * @param list<array{materia:Materia, aula:Aula, horaInicio:\DateTimeImmutable, horaFin:\DateTimeImmutable}> $franjas
     */
    private function guardAgainstOverlaps(Grupo $grupo, array $dias, array $franjas): void
    {
        $grupoId = $grupo->id ?? 0;
        foreach ($dias as $dia) {
            foreach ($franjas as $franja) {
                $aulaId = $franja['aula']->id ?? 0;
                if ($this->horarios->hasOverlap($grupoId, $aulaId, $dia, $franja['horaInicio'], $franja['horaFin'])) {
                    throw new HorarioException(sprintf(
                        'Choque de horario el dia %s en la franja %s-%s (grupo o aula ya ocupados).',
                        $dia,
                        $franja['horaInicio']->format('H:i'),
                        $franja['horaFin']->format('H:i'),
                    ));
                }
            }
        }
    }

    /**
     * @param list<string>                                                                                  $dias
     * @param list<array{materia:Materia, aula:Aula, horaInicio:\DateTimeImmutable, horaFin:\DateTimeImmutable}> $franjas
     *
     * @return list<Horario>
     */
    private function materialize(Grupo $grupo, array $dias, array $franjas): array
    {
        $horarios = [];
        foreach ($dias as $dia) {
            foreach ($franjas as $franja) {
                $horario = new Horario();
                $horario->configure($grupo, $franja['materia'], $franja['aula'], $dia, $franja['horaInicio'], $franja['horaFin']);
                $horarios[] = $horario;
            }
        }

        return $horarios;
    }

    /**
     * @param list<Horario> $horarios
     */
    private function registerAudit(Grupo $grupo, array $horarios, HorarioMasivoInput $input): void
    {
        $this->events->generadoMasivo($grupo->codigo, count($horarios), $input->reemplazar, $input->actorUserId);
    }
}
