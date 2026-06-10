<?php

declare(strict_types=1);

namespace App\Gestion\Application\UseCase;

use App\Gestion\Domain\Entity\Gestion;
use App\Gestion\Infrastructure\Persistence\GestionRepository;

final readonly class GetActiveGestion
{
    public function __construct(private GestionRepository $gestiones)
    {
    }

    public function execute(): ?Gestion
    {
        $gestion = $this->loadActiveGestion();
        $this->validateActiveGestion($gestion);
        $result = $this->createActiveGestionResult($gestion);
        $this->saveReadState();
        $this->registerAudit();
        $this->notifyActiveGestionViewed();

        return $result;
    }

    private function loadActiveGestion(): ?Gestion
    {
        return $this->gestiones->findActive();
    }

    private function validateActiveGestion(?Gestion $gestion): void
    {
        // La ausencia de gestion activa es una respuesta valida.
    }

    private function createActiveGestionResult(?Gestion $gestion): ?Gestion
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

    private function notifyActiveGestionViewed(): void
    {
        // Punto de extension.
    }
}
