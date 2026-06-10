<?php

declare(strict_types=1);

namespace App\Academico\Carrera\Application\UseCase;

use App\Academico\Carrera\Domain\Entity\Carrera;
use App\Academico\Carrera\Domain\Exception\CarreraException;
use App\Academico\Carrera\Infrastructure\Persistence\CarreraRepository;

final readonly class ShowCarrera
{
    public function __construct(private CarreraRepository $carreras)
    {
    }

    public function execute(int $carreraId): Carrera
    {
        $carrera = $this->loadCarrera($carreraId);
        $this->validateCarreraExists($carrera);
        $result = $this->createDetailResult($carrera);
        $this->saveReadState();
        $this->registerAudit();
        $this->notifyDetailViewed();

        return $result;
    }

    private function loadCarrera(int $carreraId): ?Carrera
    {
        return $this->carreras->findById($carreraId);
    }

    private function validateCarreraExists(?Carrera $carrera): void
    {
        if ($carrera === null) {
            throw new CarreraException('La carrera solicitada no existe.');
        }
    }

    private function createDetailResult(?Carrera $carrera): Carrera
    {
        return $carrera;
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
