<?php

declare(strict_types=1);

namespace App\Academico\Materia\Application\UseCase;

use App\Academico\Materia\Application\DTO\MateriaInput;
use App\Academico\Materia\Domain\Entity\Materia;
use App\Academico\Materia\Domain\Exception\MateriaException;
use App\Academico\Materia\Infrastructure\Persistence\MateriaRepository;
use App\Bitacora\Application\UseCase\RecordLogEntry;
use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Catalog\ModuleCatalog;

final readonly class UpdateMateria
{
    public function __construct(private MateriaRepository $materias, private RecordLogEntry $audit)
    {
    }

    public function execute(int $materiaId, MateriaInput $input): Materia
    {
        $materia = $this->loadMateria($materiaId);
        $sameCode = $this->loadMateriaByCode($input);
        $this->validateMateriaData($input);
        $this->validateBusinessRules($materia, $sameCode);
        $this->updateMateria($materia, $input);
        $this->saveMateria($materia);
        $this->registerAudit($materia, $input);
        $this->notifyMateriaUpdated($materia);

        return $materia;
    }

    private function loadMateria(int $materiaId): Materia
    {
        $materia = $this->materias->findById($materiaId);
        if ($materia === null) {
            throw new MateriaException('La materia solicitada no existe.');
        }

        return $materia;
    }

    private function loadMateriaByCode(MateriaInput $input): ?Materia
    {
        return $this->materias->findByCodigo($input->codigo);
    }

    private function validateMateriaData(MateriaInput $input): void
    {
        if (trim($input->codigo) === '' || trim($input->nombre) === '') {
            throw new MateriaException('Codigo y nombre de materia son obligatorios.');
        }
    }

    private function validateBusinessRules(Materia $materia, ?Materia $sameCode): void
    {
        if ($sameCode !== null && $sameCode->id !== $materia->id) {
            throw new MateriaException('Ya existe otra materia con ese codigo.');
        }
    }

    private function updateMateria(Materia $materia, MateriaInput $input): void
    {
        $materia->rename($input->codigo, $input->nombre, $input->descripcion);
    }

    private function saveMateria(Materia $materia): void
    {
        $this->materias->save($materia);
    }

    private function registerAudit(Materia $materia, MateriaInput $input): void
    {
        $this->audit->execute(ActionCatalog::UPDATE, ModuleCatalog::MATERIAS, sprintf('Se actualizo la materia %s.', $materia->codigo), $input->actorUserId);
    }

    private function notifyMateriaUpdated(Materia $materia): void
    {
        // Punto de extension.
    }
}
