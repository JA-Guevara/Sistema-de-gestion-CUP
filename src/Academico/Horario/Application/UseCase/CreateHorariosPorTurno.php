<?php

declare(strict_types=1);

namespace App\Academico\Horario\Application\UseCase;

use App\Academico\Aula\Domain\Entity\Aula;
use App\Academico\Aula\Infrastructure\Persistence\AulaRepository;
use App\Academico\Grupo\Domain\Entity\Grupo;
use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;
use App\Academico\Horario\Application\DTO\HorariosPorTurnoInput;
use App\Academico\Horario\Domain\Entity\Horario;
use App\Academico\Horario\Domain\Exception\HorarioException;
use App\Academico\Horario\Infrastructure\Persistence\HorarioRepository;
use App\Academico\Materia\Domain\Entity\Materia;
use App\Academico\Materia\Infrastructure\Persistence\MateriaRepository;
use App\Bitacora\Application\UseCase\RecordLogEntry;
use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Catalog\ModuleCatalog;

final readonly class CreateHorariosPorTurno
{
    private const DIAS_VALIDOS = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'];
    private const TURNOS_VALIDOS = ['MANANA', 'TARDE', 'NOCHE', 'PERSONALIZADO'];

    public function __construct(
        private HorarioRepository $horarios,
        private GrupoRepository $grupos,
        private MateriaRepository $materias,
        private AulaRepository $aulas,
        private RecordLogEntry $audit,
    ) {
    }

    /** @return list<Horario> */
    public function execute(HorariosPorTurnoInput $input): array
    {
        $grupo = $this->loadGrupo($input);
        $materia = $this->loadMateria($input);
        $aula = $this->loadAula($input);
        $dias = $this->validateDays($input);
        $this->validateSchedule($input);
        $this->validateBusinessRules($input, $dias);
        $horarios = $this->createHorarios($grupo, $materia, $aula, $input, $dias);
        $this->saveHorarios($horarios);
        $this->registerAudit($horarios, $input);
        $this->notifyHorariosCreated($horarios);

        return $horarios;
    }

    private function loadGrupo(HorariosPorTurnoInput $input): Grupo
    {
        $grupo = $this->grupos->findById($input->grupoId);
        if ($grupo === null) {
            throw new HorarioException('El grupo seleccionado no existe.');
        }

        return $grupo;
    }

    private function loadMateria(HorariosPorTurnoInput $input): Materia
    {
        $materia = $this->materias->findById($input->materiaId);
        if ($materia === null || !$materia->isActive()) {
            throw new HorarioException('La materia seleccionada no existe o esta inactiva.');
        }

        return $materia;
    }

    private function loadAula(HorariosPorTurnoInput $input): Aula
    {
        $aula = $this->aulas->findById($input->aulaId);
        if ($aula === null || !$aula->isActive()) {
            throw new HorarioException('El aula seleccionada no existe o esta inactiva.');
        }

        return $aula;
    }

    /** @return list<string> */
    private function validateDays(HorariosPorTurnoInput $input): array
    {
        $dias = array_values(array_unique(array_map(static fn (string $dia): string => mb_strtoupper(trim($dia)), $input->dias)));
        if ($dias === []) {
            throw new HorarioException('Debe seleccionar al menos un dia.');
        }

        foreach ($dias as $dia) {
            if (!in_array($dia, self::DIAS_VALIDOS, true)) {
                throw new HorarioException('Uno de los dias seleccionados no es valido.');
            }
        }

        return $dias;
    }

    private function validateSchedule(HorariosPorTurnoInput $input): void
    {
        if (!in_array($input->turno, self::TURNOS_VALIDOS, true)) {
            throw new HorarioException('El turno seleccionado no es valido.');
        }

        if ($input->horaInicio === null || $input->horaFin === null || $input->horaFin <= $input->horaInicio) {
            throw new HorarioException('La hora fin debe ser mayor a la hora inicio.');
        }
    }

    /** @param list<string> $dias */
    private function validateBusinessRules(HorariosPorTurnoInput $input, array $dias): void
    {
        foreach ($dias as $dia) {
            if ($this->horarios->hasOverlap($input->grupoId, $input->aulaId, $dia, $input->horaInicio, $input->horaFin)) {
                throw new HorarioException(sprintf('Existe choque de horario para el grupo o aula el dia %s.', $dia));
            }
        }
    }

    /** @param list<string> $dias @return list<Horario> */
    private function createHorarios(Grupo $grupo, Materia $materia, Aula $aula, HorariosPorTurnoInput $input, array $dias): array
    {
        $horarios = [];
        foreach ($dias as $dia) {
            $horario = new Horario();
            $horario->configure($grupo, $materia, $aula, $dia, $input->horaInicio, $input->horaFin);
            $horarios[] = $horario;
        }

        return $horarios;
    }

    /** @param list<Horario> $horarios */
    private function saveHorarios(array $horarios): void
    {
        $this->horarios->saveMany($horarios);
    }

    /** @param list<Horario> $horarios */
    private function registerAudit(array $horarios, HorariosPorTurnoInput $input): void
    {
        if ($horarios === []) {
            return;
        }

        $first = $horarios[0];
        $this->audit->execute(
            ActionCatalog::CREATE,
            ModuleCatalog::HORARIOS,
            sprintf('Se asignaron %d horarios al grupo %s para la materia %s.', count($horarios), $first->grupo->codigo, $first->materia->nombre),
            $input->actorUserId,
        );
    }

    /** @param list<Horario> $horarios */
    private function notifyHorariosCreated(array $horarios): void
    {
        // Punto de extension para notificaciones administrativas.
    }
}
