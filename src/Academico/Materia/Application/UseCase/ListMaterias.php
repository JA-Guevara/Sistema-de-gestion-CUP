<?php

declare(strict_types=1);

namespace App\Academico\Materia\Application\UseCase;

use App\Academico\Materia\Infrastructure\Persistence\MateriaRepository;

final readonly class ListMaterias
{
    public function __construct(private MateriaRepository $materias)
    {
    }

    public function execute(): array
    {
        return $this->materias->listAll();
    }
}
