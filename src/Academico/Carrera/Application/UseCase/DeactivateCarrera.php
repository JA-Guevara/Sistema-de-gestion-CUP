<?php

declare(strict_types=1);

namespace App\Academico\Carrera\Application\UseCase;

use App\Academico\Carrera\Application\DTO\CarreraActionInput;
use App\Academico\Carrera\Domain\Entity\Carrera;
use App\Academico\Carrera\Domain\Exception\CarreraException;
use App\Academico\Carrera\Infrastructure\Persistence\CarreraRepository;
use App\Bitacora\Application\UseCase\RecordLogEntry;
use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Catalog\ModuleCatalog;

final readonly class DeactivateCarrera
{
    public function __construct(
        private CarreraRepository $carreras,
        private RecordLogEntry $audit,
    ) {
    }

    public function execute(CarreraActionInput $input): Carrera
    {
        $carrera = $this->loadCarrera($input);
        $this->validateCarreraState($carrera);
        $this->validateBusinessRules($carrera);
        $this->deactivateCarrera($carrera);
        $this->saveCarrera($carrera);
        $this->registerAudit($carrera, $input);
        $this->notifyCarreraDeactivated($carrera);

        return $carrera;
    }

    private function loadCarrera(CarreraActionInput $input): Carrera
    {
        $carrera = $this->carreras->findById($input->carreraId);
        if ($carrera === null) {
            throw new CarreraException('La carrera solicitada no existe.');
        }

        return $carrera;
    }

    private function validateCarreraState(Carrera $carrera): void
    {
        if (!$carrera->isActive()) {
            throw new CarreraException('La carrera ya esta inactiva.');
        }
    }

    private function validateBusinessRules(Carrera $carrera): void
    {
        // Futuro: impedir baja si tiene inscripciones activas.
    }

    private function deactivateCarrera(Carrera $carrera): void
    {
        $carrera->deactivate();
    }

    private function saveCarrera(Carrera $carrera): void
    {
        $this->carreras->save($carrera);
    }

    private function registerAudit(Carrera $carrera, CarreraActionInput $input): void
    {
        $this->audit->execute(ActionCatalog::UPDATE, ModuleCatalog::CARRERAS, sprintf('Se desactivo la carrera %s.', $carrera->codigo), $input->actorUserId);
    }

    private function notifyCarreraDeactivated(Carrera $carrera): void
    {
        // Punto de extension.
    }
}
