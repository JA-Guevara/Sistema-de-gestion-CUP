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

final readonly class UpdateCarrera
{
    public function __construct(
        private CarreraRepository $carreras,
        private RecordLogEntry $audit,
    ) {
    }

    public function execute(int $carreraId, CarreraInput $input): Carrera
    {
        $carrera = $this->loadCarrera($carreraId);
        $sameCodeCarrera = $this->loadCarreraByCode($input);
        $this->validateCarreraData($input);
        $this->validateConfiguration($input);
        $this->validateBusinessRules($carrera, $sameCodeCarrera);
        $this->updateCarrera($carrera, $input);
        $this->saveCarrera($carrera);
        $this->registerAudit($carrera, $input);
        $this->notifyCarreraUpdated($carrera);

        return $carrera;
    }

    private function loadCarrera(int $carreraId): Carrera
    {
        $carrera = $this->carreras->findById($carreraId);
        if ($carrera === null) {
            throw new CarreraException('La carrera solicitada no existe.');
        }

        return $carrera;
    }

    private function loadCarreraByCode(CarreraInput $input): ?Carrera
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

    private function validateBusinessRules(Carrera $carrera, ?Carrera $sameCodeCarrera): void
    {
        if ($sameCodeCarrera !== null && $sameCodeCarrera->id !== $carrera->id) {
            throw new CarreraException('Ya existe otra carrera con ese codigo.');
        }
    }

    private function updateCarrera(Carrera $carrera, CarreraInput $input): void
    {
        $carrera->rename($input->codigo, $input->nombre, $input->descripcion);
        $carrera->configure($input->facultad, $input->modalidad);
    }

    private function saveCarrera(Carrera $carrera): void
    {
        $this->carreras->save($carrera);
    }

    private function registerAudit(Carrera $carrera, CarreraInput $input): void
    {
        $this->audit->execute(ActionCatalog::UPDATE, ModuleCatalog::CARRERAS, sprintf('Se actualizo la carrera %s.', $carrera->codigo), $input->actorUserId);
    }

    private function notifyCarreraUpdated(Carrera $carrera): void
    {
        // Punto de extension para notificaciones administrativas.
    }
}
