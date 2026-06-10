<?php

declare(strict_types=1);

namespace App\Academico\Grupo\Application\UseCase;

use App\Academico\Grupo\Domain\Entity\Grupo;
use App\Academico\Grupo\Domain\Exception\GrupoException;
use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;

final readonly class ShowGrupo
{
    public function __construct(private GrupoRepository $grupos)
    {
    }

    public function execute(int $grupoId): Grupo
    {
        $grupo = $this->grupos->findById($grupoId);
        if ($grupo === null) {
            throw new GrupoException('El grupo solicitado no existe.');
        }

        return $grupo;
    }
}
