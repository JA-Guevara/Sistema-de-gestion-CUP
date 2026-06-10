<?php

declare(strict_types=1);

namespace App\Gestion\Application\UseCase;

use App\Gestion\Domain\Entity\Gestion;
use App\Gestion\Infrastructure\Persistence\GestionRepository;

final readonly class GetGestionDashboard
{
    public function __construct(private GestionRepository $gestiones)
    {
    }

    public function execute(): array
    {
        $activeGestion = $this->loadActiveGestion();
        $this->validateDashboardRules($activeGestion);
        $dashboard = $this->createDashboard($activeGestion);
        $this->saveReadState();
        $this->registerAudit();
        $this->notifyDashboardViewed();

        return $dashboard;
    }

    private function loadActiveGestion(): ?Gestion
    {
        return $this->gestiones->findActive();
    }

    private function validateDashboardRules(?Gestion $gestion): void
    {
        // La pantalla puede mostrarse aunque no exista gestion activa.
    }

    private function createDashboard(?Gestion $gestion): array
    {
        return [
            'activeGestion' => $gestion,
            'enabledCareers' => $gestion !== null ? $this->countEnabledCareers($gestion) : 0,
            'totalQuota' => $gestion?->configuracion?->cupoTotal ?? 0,
            'availableQuota' => $this->resolveAvailableQuota($gestion),
        ];
    }

    private function countEnabledCareers(Gestion $gestion): int
    {
        return count(array_filter($gestion->carreras->toArray(), static fn ($carrera): bool => $carrera->habilitada));
    }

    private function resolveAvailableQuota(?Gestion $gestion): int
    {
        if ($gestion === null || $gestion->cupos->isEmpty()) {
            return 0;
        }

        return $gestion->cupos->first()->disponibles;
    }

    private function saveReadState(): void
    {
        // Consulta sin efectos de persistencia.
    }

    private function registerAudit(): void
    {
        // Consulta de lectura sin auditoria funcional por ahora.
    }

    private function notifyDashboardViewed(): void
    {
        // Punto de extension.
    }
}
