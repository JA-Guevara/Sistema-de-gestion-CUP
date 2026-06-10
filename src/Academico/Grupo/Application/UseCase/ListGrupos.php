<?php

declare(strict_types=1);

namespace App\Academico\Grupo\Application\UseCase;

use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;

final readonly class ListGrupos
{
    public function __construct(private GrupoRepository $grupos)
    {
    }

    public function execute(): array
    {
        return $this->grupos->listAll();
    }
}
