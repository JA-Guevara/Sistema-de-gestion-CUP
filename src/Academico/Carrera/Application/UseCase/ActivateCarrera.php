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

final readonly class ActivateCarrera
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
        $this->activateCarrera($carrera);
        $this->saveCarrera($carrera);
        $this->registerAudit($carrera, $input);
        $this->notifyCarreraActivated($carrera);

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
        if ($carrera->isActive()) {
            throw new CarreraException('La carrera ya esta activa.');
        }
    }

    private function validateBusinessRules(Carrera $carrera): void
    {
        // Futuro: validar requisitos academicos antes de activar.
    }

    private function activateCarrera(Carrera $carrera): void
    {
        $carrera->activate();
    }

    private function saveCarrera(Carrera $carrera): void
    {
        $this->carreras->save($carrera);
    }

    private function registerAudit(Carrera $carrera, CarreraActionInput $input): void
    {
        $this->audit->execute(ActionCatalog::UPDATE, ModuleCatalog::CARRERAS, sprintf('Se activo la carrera %s.', $carrera->codigo), $input->actorUserId);
    }

    private function notifyCarreraActivated(Carrera $carrera): void
    {
        // Punto de extension.
    }
}
