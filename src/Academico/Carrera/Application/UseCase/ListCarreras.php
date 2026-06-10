<?php

declare(strict_types=1);

namespace App\Academico\Carrera\Application\UseCase;

use App\Academico\Carrera\Domain\Entity\Carrera;
use App\Academico\Carrera\Infrastructure\Persistence\CarreraRepository;

final readonly class ListCarreras
{
    public function __construct(private CarreraRepository $carreras)
    {
    }

    /** @return list<Carrera> */
    public function execute(): array
    {
        $carreras = $this->loadCarreras();
        $this->validateReadModel($carreras);
        $items = $this->createListResult($carreras);
        $this->saveReadState();
        $this->registerAudit();
        $this->notifyListViewed();

        return $items;
    }

    private function loadCarreras(): array
    {
        return $this->carreras->listAll();
    }

    private function validateReadModel(array $carreras): void
    {
        // No hay reglas de negocio para listar carreras por ahora.
    }

    private function createListResult(array $carreras): array
    {
        return $carreras;
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
