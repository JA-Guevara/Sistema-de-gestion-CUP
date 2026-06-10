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

final readonly class UpdateAula
{
    public function __construct(private AulaRepository $aulas, private RecordLogEntry $audit)
    {
    }

    public function execute(int $aulaId, AulaInput $input): Aula
    {
        $aula = $this->loadAula($aulaId);
        $sameCode = $this->loadAulaByCode($input);
        $this->validateAulaData($input);
        $this->validateBusinessRules($aula, $sameCode);
        $this->updateAula($aula, $input);
        $this->saveAula($aula);
        $this->registerAudit($aula, $input);
        $this->notifyAulaUpdated($aula);

        return $aula;
    }

    private function loadAula(int $aulaId): Aula
    {
        $aula = $this->aulas->findById($aulaId);
        if ($aula === null) {
            throw new AulaException('El aula solicitada no existe.');
        }

        return $aula;
    }

    private function loadAulaByCode(AulaInput $input): ?Aula
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

    private function validateBusinessRules(Aula $aula, ?Aula $sameCode): void
    {
        if ($sameCode !== null && $sameCode->id !== $aula->id) {
            throw new AulaException('Ya existe otra aula con ese codigo.');
        }
    }

    private function updateAula(Aula $aula, AulaInput $input): void
    {
        $aula->updateData($input->codigo, $input->nombre, $input->piso, $input->capacidad, $input->ubicacion);
    }

    private function saveAula(Aula $aula): void
    {
        $this->aulas->save($aula);
    }

    private function registerAudit(Aula $aula, AulaInput $input): void
    {
        $this->audit->execute(ActionCatalog::UPDATE, ModuleCatalog::AULAS, sprintf('Se actualizo el aula %s.', $aula->codigo), $input->actorUserId);
    }

    private function notifyAulaUpdated(Aula $aula): void
    {
        // Punto de extension.
    }
}
