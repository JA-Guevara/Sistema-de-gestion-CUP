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

final readonly class CreateMateria
{
    public function __construct(private MateriaRepository $materias, private RecordLogEntry $audit)
    {
    }

    public function execute(MateriaInput $input): Materia
    {
        $existing = $this->loadExistingMateria($input);
        $this->validateMateriaData($input);
        $this->validateBusinessRules($existing);
        $materia = $this->createMateria($input);
        $this->saveMateria($materia);
        $this->registerAudit($materia, $input);
        $this->notifyMateriaCreated($materia);

        return $materia;
    }

    private function loadExistingMateria(MateriaInput $input): ?Materia
    {
        return $this->materias->findByCodigo($input->codigo);
    }

    private function validateMateriaData(MateriaInput $input): void
    {
        if (trim($input->codigo) === '' || trim($input->nombre) === '') {
            throw new MateriaException('Codigo y nombre de materia son obligatorios.');
        }
    }

    private function validateBusinessRules(?Materia $existing): void
    {
        if ($existing !== null) {
            throw new MateriaException('Ya existe una materia con ese codigo.');
        }
    }

    private function createMateria(MateriaInput $input): Materia
    {
        $materia = new Materia();
        $materia->rename($input->codigo, $input->nombre, $input->descripcion, $input->area);

        return $materia;
    }

    private function saveMateria(Materia $materia): void
    {
        $this->materias->save($materia);
    }

    private function registerAudit(Materia $materia, MateriaInput $input): void
    {
        $this->audit->execute(ActionCatalog::CREATE, ModuleCatalog::MATERIAS, sprintf('Se creo la materia %s.', $materia->codigo), $input->actorUserId);
    }

    private function notifyMateriaCreated(Materia $materia): void
    {
        // Punto de extension.
    }
}
