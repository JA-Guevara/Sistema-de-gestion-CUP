<?php

declare(strict_types=1);

namespace App\Academico\Aula\Application\UseCase;

use App\Academico\Aula\Application\DTO\AulaInput;
use App\Academico\Aula\Domain\Entity\Aula;
use App\Academico\Aula\Domain\Exception\AulaException;
use App\Academico\Aula\Infrastructure\Persistence\AulaRepository;
use App\Bitacora\Application\UseCase\RecordLogEntry;
use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Catalog\ModuleCatalog;

final readonly class CreateAula
{
    public function __construct(private AulaRepository $aulas, private RecordLogEntry $audit)
    {
    }

    public function execute(AulaInput $input): Aula
    {
        $existing = $this->loadExistingAula($input);
        $this->validateAulaData($input);
        $this->validateBusinessRules($existing);
        $aula = $this->createAula($input);
        $this->saveAula($aula);
        $this->registerAudit($aula, $input);
        $this->notifyAulaCreated($aula);

        return $aula;
    }

    private function loadExistingAula(AulaInput $input): ?Aula
    {
        return $this->aulas->findByCodigo($input->codigo);
    }

    private function validateAulaData(AulaInput $input): void
    {
        if (trim($input->codigo) === '' || trim($input->nombre) === '') {
            throw new AulaException('Codigo y nombre de aula son obligatorios.');
        }

        if ($input->piso < 0 || $input->capacidad <= 0) {
            throw new AulaException('El piso no puede ser negativo y la capacidad debe ser mayor a cero.');
        }
    }

    private function validateBusinessRules(?Aula $existing): void
    {
        if ($existing !== null) {
            throw new AulaException('Ya existe un aula con ese codigo.');
        }
    }

    private function createAula(AulaInput $input): Aula
    {
        $aula = new Aula();
        $aula->updateData($input->codigo, $input->nombre, $input->piso, $input->capacidad, $input->ubicacion);

        return $aula;
    }

    private function saveAula(Aula $aula): void
    {
        $this->aulas->save($aula);
    }

    private function registerAudit(Aula $aula, AulaInput $input): void
    {
        $this->audit->execute(ActionCatalog::CREATE, ModuleCatalog::AULAS, sprintf('Se creo el aula %s.', $aula->codigo), $input->actorUserId);
    }

    private function notifyAulaCreated(Aula $aula): void
    {
        // Punto de extension.
    }
}
