<?php

declare(strict_types=1);

namespace App\Gestion\Application\UseCase;

use App\Gestion\Domain\Entity\Gestion;
use App\Gestion\Domain\Exception\GestionException;
use App\Gestion\Infrastructure\Persistence\GestionRepository;

final readonly class ShowGestion
{
    public function __construct(private GestionRepository $gestiones)
    {
    }

    public function execute(int $gestionId): Gestion
    {
        $gestion = $this->loadGestion($gestionId);
        $this->validateGestionExists($gestion);
        $result = $this->createDetailResult($gestion);
        $this->saveReadState();
        $this->registerAudit();
        $this->notifyDetailViewed();

        return $result;
    }

    private function loadGestion(int $gestionId): ?Gestion
    {
        return $this->gestiones->findById($gestionId);
    }

    private function validateGestionExists(?Gestion $gestion): void
    {
        if ($gestion === null) {
            throw new GestionException('La gestion solicitada no existe.');
        }
    }

    private function createDetailResult(?Gestion $gestion): Gestion
    {
        return $gestion;
    }

    private function saveReadState(): void
    {
        // Consulta sin efectos de persistencia.
    }

    private function registerAudit(): void
    {
        // Consulta de lectura sin auditoria funcional por ahora.
    }

    private function notifyDetailViewed(): void
    {
        // Punto de extension.
    }
}
