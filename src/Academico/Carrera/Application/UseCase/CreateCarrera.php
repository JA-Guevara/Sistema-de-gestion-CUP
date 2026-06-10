<?php

declare(strict_types=1);

namespace App\Academico\Carrera\Application\UseCase;

use App\Academico\Carrera\Application\DTO\CarreraInput;
use App\Academico\Carrera\Domain\Entity\Carrera;
use App\Academico\Carrera\Domain\Exception\CarreraException;
use App\Academico\Carrera\Infrastructure\Persistence\CarreraRepository;
use App\Bitacora\Application\UseCase\RecordLogEntry;
use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Catalog\ModuleCatalog;

final readonly class CreateCarrera
{
    public function __construct(
        private CarreraRepository $carreras,
        private RecordLogEntry $audit,
    ) {
    }

    public function execute(CarreraInput $input): Carrera
    {
        $existingCarrera = $this->loadExistingCarrera($input);
        $this->validateCarreraData($input);
        $this->validateConfiguration($input);
        $this->validateBusinessRules($existingCarrera);
        $carrera = $this->createCarrera($input);
        $this->saveCarrera($carrera);
        $this->registerAudit($carrera, $input);
        $this->notifyCarreraCreated($carrera);

        return $carrera;
    }

    private function loadExistingCarrera(CarreraInput $input): ?Carrera
    {
        return $this->carreras->findByCodigo($input->codigo);
    }

    private function validateCarreraData(CarreraInput $input): void
    {
        if (trim($input->codigo) === '' || trim($input->nombre) === '') {
            throw new CarreraException('Codigo y nombre de carrera son obligatorios.');
        }
    }

    private function validateConfiguration(CarreraInput $input): void
    {
        if ($input->modalidad === null) {
            throw new CarreraException('La modalidad de la carrera es obligatoria.');
        }

        if (!in_array($input->modalidad, ['Presencial', 'Virtual', 'Semipresencial'], true)) {
            throw new CarreraException('La modalidad seleccionada no es valida.');
        }
    }

    private function validateBusinessRules(?Carrera $existingCarrera): void
    {
        if ($existingCarrera !== null) {
            throw new CarreraException('Ya existe una carrera con ese codigo.');
        }
    }

    private function createCarrera(CarreraInput $input): Carrera
    {
        $carrera = new Carrera();
        $carrera->rename($input->codigo, $input->nombre, $input->descripcion);
        $carrera->configure($input->facultad, $input->modalidad);

        return $carrera;
    }

    private function saveCarrera(Carrera $carrera): void
    {
        $this->carreras->save($carrera);
    }

    private function registerAudit(Carrera $carrera, CarreraInput $input): void
    {
        $this->audit->execute(ActionCatalog::CREATE, ModuleCatalog::CARRERAS, sprintf('Se creo la carrera %s.', $carrera->codigo), $input->actorUserId);
    }

    private function notifyCarreraCreated(Carrera $carrera): void
    {
        // Punto de extension para notificaciones administrativas.
    }
}
