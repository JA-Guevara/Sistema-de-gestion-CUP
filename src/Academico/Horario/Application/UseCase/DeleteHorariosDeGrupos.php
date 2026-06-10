<?php

declare(strict_types=1);

namespace App\Academico\Horario\Application\UseCase;

use App\Academico\Grupo\Domain\Entity\Grupo;
use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;
use App\Academico\Horario\Application\DTO\HorariosGrupoBulkDeleteInput;
use App\Academico\Horario\Domain\Exception\HorarioException;
use App\Academico\Horario\Infrastructure\Persistence\HorarioRepository;
use App\Bitacora\Application\EventLog\HorarioEvents;

final readonly class DeleteHorariosDeGrupos
{
    public function __construct(
        private HorarioRepository $horarios,
        private GrupoRepository $grupos,
        private HorarioEvents $events,
    ) {
    }

    public function execute(HorariosGrupoBulkDeleteInput $input): int
    {
        $grupos = $this->loadGrupos($input);
        $this->validateBusinessRules($input, $grupos);
        $total = $this->deleteHorarios($grupos);
        $this->registerAudit($grupos, $total, $input);
        $this->notifyHorariosDeleted($total);

        return $total;
    }

    /**
     * @return list<Grupo>
     */
    private function loadGrupos(HorariosGrupoBulkDeleteInput $input): array
    {
        return $this->grupos->findByIds($input->grupoIds);
    }

    /**
     * @param list<Grupo> $grupos
     */
    private function validateBusinessRules(HorariosGrupoBulkDeleteInput $input, array $grupos): void
    {
        if ($input->grupoIds === []) {
            throw new HorarioException('Debe seleccionar al menos un grupo.');
        }

        if (count($grupos) !== count(array_unique($input->grupoIds))) {
            throw new HorarioException('Uno o mas grupos seleccionados no existen.');
        }
    }

    /**
     * @param list<Grupo> $grupos
     */
    private function deleteHorarios(array $grupos): int
    {
        $total = 0;
        foreach ($grupos as $grupo) {
            $total += $this->horarios->deleteByGrupo($grupo->id ?? 0);
        }

        return $total;
    }

    /**
     * @param list<Grupo> $grupos
     */
    private function registerAudit(array $grupos, int $total, HorariosGrupoBulkDeleteInput $input): void
    {
        $this->events->eliminadosGrupos(count($grupos), $total, $input->actorUserId);
    }

    private function notifyHorariosDeleted(int $total): void
    {
        // Punto de extension.
    }
}
