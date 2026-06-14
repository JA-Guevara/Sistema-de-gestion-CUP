<?php

declare(strict_types=1);

namespace App\Notas\Application\UseCase;

use App\Notas\Domain\Entity\AsignacionDocente;
use App\Notas\Infrastructure\Persistence\AsignacionDocenteRepository;

final readonly class ListAsignaciones
{
    public function __construct(private AsignacionDocenteRepository $asignaciones)
    {
    }

    /** @return list<AsignacionDocente> */
    public function execute(int $gestionId): array
    {
        return $this->asignaciones->listByGestion($gestionId);
    }
}
