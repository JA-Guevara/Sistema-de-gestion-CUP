<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;

final readonly class ListInscripciones
{
    public function __construct(private InscripcionRepository $inscripciones)
    {
    }

    /** @return list<\App\Inscripcion\Domain\Entity\Inscripcion> */
    public function execute(int $gestionId): array
    {
        return $this->inscripciones->listByGestion($gestionId);
    }
}
