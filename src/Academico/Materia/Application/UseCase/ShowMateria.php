<?php

declare(strict_types=1);

namespace App\Academico\Materia\Application\UseCase;

use App\Academico\Materia\Domain\Entity\Materia;
use App\Academico\Materia\Domain\Exception\MateriaException;
use App\Academico\Materia\Infrastructure\Persistence\MateriaRepository;

final readonly class ShowMateria
{
    public function __construct(private MateriaRepository $materias)
    {
    }

    public function execute(int $materiaId): Materia
    {
        $materia = $this->materias->findById($materiaId);
        if ($materia === null) {
            throw new MateriaException('La materia solicitada no existe.');
        }

        return $materia;
    }
}
