<?php

declare(strict_types=1);

namespace App\Academico\Horario\Application\UseCase;

use App\Academico\Horario\Application\DTO\HorarioActionInput;
use App\Academico\Horario\Domain\Entity\Horario;
use App\Academico\Horario\Domain\Exception\HorarioException;
use App\Academico\Horario\Infrastructure\Persistence\HorarioRepository;
use App\Bitacora\Application\UseCase\RecordLogEntry;
use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Catalog\ModuleCatalog;

final readonly class DeleteHorario
{
    public function __construct(
        private HorarioRepository $horarios,
        private RecordLogEntry $audit,
    ) {
    }

    public function execute(HorarioActionInput $input): void
    {
        $horario = $this->loadHorario($input);
        $detalle = $this->buildAuditDetail($horario);
        $this->deleteHorario($horario);
        $this->registerAudit($detalle, $input);
        $this->notifyHorarioDeleted();
    }

    private function loadHorario(HorarioActionInput $input): Horario
    {
        $horario = $this->horarios->findById($input->horarioId);
        if ($horario === null) {
            throw new HorarioException('El horario solicitado no existe.');
        }

        return $horario;
    }

    private function buildAuditDetail(Horario $horario): string
    {
        return sprintf(
            'Se elimino horario de grupo %s, materia %s, dia %s, %s-%s.',
            $horario->grupo->codigo,
            $horario->materia->nombre,
            $horario->dia,
            $horario->horaInicio->format('H:i'),
            $horario->horaFin->format('H:i'),
        );
    }

    private function deleteHorario(Horario $horario): void
    {
        $this->horarios->remove($horario);
    }

    private function registerAudit(string $detalle, HorarioActionInput $input): void
    {
        $this->audit->execute(ActionCatalog::DELETE, ModuleCatalog::HORARIOS, $detalle, $input->actorUserId);
    }

    private function notifyHorarioDeleted(): void
    {
        // Punto de extension.
    }
}
