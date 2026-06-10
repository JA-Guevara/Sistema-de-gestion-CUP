<?php

declare(strict_types=1);

namespace App\Academico\Horario\Application\UseCase;

use App\Academico\Aula\Domain\Entity\Aula;
use App\Academico\Aula\Infrastructure\Persistence\AulaRepository;
use App\Academico\Grupo\Domain\Entity\Grupo;
use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;
use App\Academico\Horario\Application\DTO\HorarioInput;
use App\Academico\Horario\Domain\Entity\Horario;
use App\Academico\Horario\Domain\Exception\HorarioException;
use App\Academico\Horario\Infrastructure\Persistence\HorarioRepository;
use App\Academico\Materia\Domain\Entity\Materia;
use App\Academico\Materia\Infrastructure\Persistence\MateriaRepository;
use App\Bitacora\Application\UseCase\RecordLogEntry;
use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Catalog\ModuleCatalog;

final readonly class UpdateHorario
{
    public function __construct(
        private HorarioRepository $horarios,
        private GrupoRepository $grupos,
        private MateriaRepository $materias,
        private AulaRepository $aulas,
        private RecordLogEntry $audit,
    ) {
    }

    public function execute(int $horarioId, HorarioInput $input): Horario
    {
        $horario = $this->loadHorario($horarioId);
        $grupo = $this->loadGrupo($input);
        $materia = $this->loadMateria($input);
        $aula = $this->loadAula($input);
        $this->validateHorarioData($input);
        $this->validateBusinessRules($horario, $input);
        $this->updateHorario($horario, $grupo, $materia, $aula, $input);
        $this->saveHorario($horario);
        $this->registerAudit($horario, $input);
        $this->notifyHorarioUpdated($horario);

        return $horario;
    }

    private function loadHorario(int $horarioId): Horario
    {
        $horario = $this->horarios->findById($horarioId);
        if ($horario === null) {
            throw new HorarioException('El horario solicitado no existe.');
        }

        return $horario;
    }

    private function loadGrupo(HorarioInput $input): Grupo
    {
        $grupo = $this->grupos->findById($input->grupoId);
        if ($grupo === null) {
            throw new HorarioException('El grupo seleccionado no existe.');
        }

        return $grupo;
    }

    private function loadMateria(HorarioInput $input): Materia
    {
        $materia = $this->materias->findById($input->materiaId);
        if ($materia === null || !$materia->isActive()) {
            throw new HorarioException('La materia seleccionada no existe o esta inactiva.');
        }

        return $materia;
    }

    private function loadAula(HorarioInput $input): Aula
    {
        $aula = $this->aulas->findById($input->aulaId);
        if ($aula === null || !$aula->isActive()) {
            throw new HorarioException('El aula seleccionada no existe o esta inactiva.');
        }

        return $aula;
    }

    private function validateHorarioData(HorarioInput $input): void
    {
        if (trim($input->dia) === '' || $input->horaInicio === null || $input->horaFin === null) {
            throw new HorarioException('Dia, hora de inicio y hora de fin son obligatorios.');
        }

        if ($input->horaFin <= $input->horaInicio) {
            throw new HorarioException('La hora fin debe ser mayor a la hora inicio.');
        }
    }

    private function validateBusinessRules(Horario $horario, HorarioInput $input): void
    {
        if ($this->horarios->hasOverlap($input->grupoId, $input->aulaId, $input->dia, $input->horaInicio, $input->horaFin, $horario->id)) {
            throw new HorarioException('El grupo o aula ya tiene un horario en esa franja.');
        }
    }

    private function updateHorario(Horario $horario, Grupo $grupo, Materia $materia, Aula $aula, HorarioInput $input): void
    {
        $horario->configure($grupo, $materia, $aula, $input->dia, $input->horaInicio, $input->horaFin);
    }

    private function saveHorario(Horario $horario): void
    {
        $this->horarios->save($horario);
    }

    private function registerAudit(Horario $horario, HorarioInput $input): void
    {
        $detalle = sprintf('Se edito horario %s para grupo %s, materia %s.', $horario->dia, $horario->grupo->codigo, $horario->materia->nombre);
        $this->audit->execute(ActionCatalog::UPDATE, ModuleCatalog::HORARIOS, $detalle, $input->actorUserId);
    }

    private function notifyHorarioUpdated(Horario $horario): void
    {
        // Punto de extension.
    }
}
