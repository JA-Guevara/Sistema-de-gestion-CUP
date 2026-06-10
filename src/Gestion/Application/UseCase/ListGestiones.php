<?php

declare(strict_types=1);

namespace App\Gestion\Application\UseCase;

use App\Gestion\Domain\Entity\Gestion;
use App\Gestion\Infrastructure\Persistence\GestionRepository;

final readonly class ListGestiones
{
    public function __construct(private GestionRepository $gestiones)
    {
    }

    /** @return list<Gestion> */
    public function execute(): array
    {
        $gestiones = $this->loadGestiones();
        $this->validateReadModel($gestiones);
        $items = $this->createListResult($gestiones);
        $this->saveReadState();
        $this->registerAudit();
        $this->notifyListViewed();

        return $items;
    }

    private function loadGestiones(): array
    {
        return $this->gestiones->listAll();
    }

    private function validateReadModel(array $gestiones): void
    {
        // No hay reglas de negocio para listar gestiones por ahora.
    }

    private function createListResult(array $gestiones): array
    {
        return $gestiones;
    }

    private function saveReadState(): void
    {
        // Consulta sin efectos de persistencia.
    }

    private function registerAudit(): void
    {
        // Consulta de lectura sin auditoria funcional por ahora.
    }

    private function notifyListViewed(): void
    {
        // Punto de extension.
    }
}
