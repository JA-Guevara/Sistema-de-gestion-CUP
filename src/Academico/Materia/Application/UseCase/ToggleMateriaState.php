<?php

declare(strict_types=1);

namespace App\Academico\Materia\Application\UseCase;

use App\Academico\Materia\Application\DTO\MateriaActionInput;
use App\Academico\Materia\Domain\Entity\Materia;
use App\Academico\Materia\Domain\Exception\MateriaException;
use App\Academico\Materia\Infrastructure\Persistence\MateriaRepository;
use App\Bitacora\Application\UseCase\RecordLogEntry;
use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Catalog\ModuleCatalog;

final readonly class ToggleMateriaState
{
    public function __construct(private MateriaRepository $materias, private RecordLogEntry $audit)
    {
    }

    public function execute(MateriaActionInput $input): Materia
    {
        $materia = $this->loadMateria($input);
        $this->validateBusinessRules($materia);
        $this->updateMateriaState($materia);
        $this->saveMateria($materia);
        $this->registerAudit($materia, $input);
        $this->notifyMateriaStateChanged($materia);

        return $materia;
    }

    private function loadMateria(MateriaActionInput $input): Materia
    {
        $materia = $this->materias->findById($input->materiaId);
        if ($materia === null) {
            throw new MateriaException('La materia solicitada no existe.');
        }

        return $materia;
    }

    private function validateBusinessRules(Materia $materia): void
    {
        // Futuro: validar que no este en evaluaciones activas antes de desactivar.
    }

    private function updateMateriaState(Materia $materia): void
    {
        $materia->isActive() ? $materia->deactivate() : $materia->activate();
    }

    private function saveMateria(Materia $materia): void
    {
        $this->materias->save($materia);
    }

    private function registerAudit(Materia $materia, MateriaActionInput $input): void
    {
        $this->audit->execute(ActionCatalog::UPDATE, ModuleCatalog::MATERIAS, sprintf('Se cambio el estado de la materia %s a %s.', $materia->codigo, $materia->estado), $input->actorUserId);
    }

    private function notifyMateriaStateChanged(Materia $materia): void
    {
        // Punto de extension.
    }
}
