<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;

final readonly class BuscarPostulante
{
    public function __construct(
        private InscripcionRepository $inscripciones,
        private GestionRepository $gestiones,
    ) {
    }

    /** @return list<\App\Inscripcion\Domain\Entity\Inscripcion> */
    public function execute(string $term): array
    {
        $gestion = $this->gestiones->findActive();

        if ($gestion === null) {
            return [];
        }

        return $this->inscripciones->search($term, $gestion->id);
    }
}
