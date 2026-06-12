<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Auth\Entity\User;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Inscripcion\Domain\Entity\Inscripcion;
use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;

final readonly class VerInscripcion
{
    public function __construct(
        private InscripcionRepository $inscripciones,
        private GestionRepository $gestiones,
    ) {
    }

    public function execute(User $user): ?Inscripcion
    {
        $gestion = $this->gestiones->findActive();

        return $gestion !== null
            ? $this->inscripciones->findByUserAndGestion($user->id, $gestion->id)
            : null;
    }

    public function executeById(int $id): Inscripcion
    {
        $inscripcion = $this->inscripciones->findById($id);
        if ($inscripcion === null) {
            throw new InscripcionException('Inscripcion no encontrada.');
        }

        return $inscripcion;
    }
}
