<?php

declare(strict_types=1);

namespace App\Academico\Aula\Application\UseCase;

use App\Academico\Aula\Application\DTO\AulaActionInput;
use App\Academico\Aula\Domain\Entity\Aula;
use App\Academico\Aula\Domain\Exception\AulaException;
use App\Academico\Aula\Infrastructure\Persistence\AulaRepository;
use App\Bitacora\Application\UseCase\RecordLogEntry;
use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Catalog\ModuleCatalog;

final readonly class ToggleAulaState
{
    public function __construct(private AulaRepository $aulas, private RecordLogEntry $audit)
    {
    }

    public function execute(AulaActionInput $input): Aula
    {
        $aula = $this->loadAula($input);
        $this->validateBusinessRules($aula);
        $this->updateAulaState($aula);
        $this->saveAula($aula);
        $this->registerAudit($aula, $input);
        $this->notifyAulaStateChanged($aula);

        return $aula;
    }

    private function loadAula(AulaActionInput $input): Aula
    {
        $aula = $this->aulas->findById($input->aulaId);
        if ($aula === null) {
            throw new AulaException('El aula solicitada no existe.');
        }

        return $aula;
    }

    private function validateBusinessRules(Aula $aula): void
    {
        // Futuro: impedir baja si tiene horarios activos.
    }

    private function updateAulaState(Aula $aula): void
    {
        $aula->isActive() ? $aula->deactivate() : $aula->activate();
    }

    private function saveAula(Aula $aula): void
    {
        $this->aulas->save($aula);
    }

    private function registerAudit(Aula $aula, AulaActionInput $input): void
    {
        $this->audit->execute(ActionCatalog::UPDATE, ModuleCatalog::AULAS, sprintf('Se cambio el estado del aula %s a %s.', $aula->codigo, $aula->estado), $input->actorUserId);
    }

    private function notifyAulaStateChanged(Aula $aula): void
    {
        // Punto de extension.
    }
}
