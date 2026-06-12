<?php

declare(strict_types=1);

namespace App\Dashboard\Application\UseCase;

use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;

final readonly class GetDashboardData
{
    public function __construct(
        private GestionRepository $gestiones,
        private InscripcionRepository $inscripciones,
        private GrupoRepository $grupos,
    ) {
    }

    public function execute(): array
    {
        $gestion = $this->gestiones->findActive();

        $totalInscritos = 0;
        $totalGrupos = 0;
        $cupoTotal = 0;
        $cupoDisponible = 0;
        $carrerasHabilitadas = 0;

        if ($gestion !== null) {
            $totalInscritos = $this->inscripciones->countByGestion($gestion->id);
            $totalGrupos = $this->grupos->countByGestion($gestion->id);
            $cupoTotal = $gestion->configuracion?->cupoTotal ?? 0;
            $cupoDisponible = !$gestion->cupos->isEmpty() ? $gestion->cupos->first()->disponibles : 0;
            $carrerasHabilitadas = count(
                array_filter($gestion->carreras->toArray(), static fn ($c) => $c->habilitada)
            );
        }

        return [
            'gestion' => $gestion,
            'totalInscritos' => $totalInscritos,
            'totalGrupos' => $totalGrupos,
            'cupoTotal' => $cupoTotal,
            'cupoDisponible' => $cupoDisponible,
            'cupoOcupado' => $cupoTotal - $cupoDisponible,
            'porcentajeOcupacion' => $cupoTotal > 0 ? round(($cupoTotal - $cupoDisponible) / $cupoTotal * 100, 1) : 0,
            'carrerasHabilitadas' => $carrerasHabilitadas,
            'totalAprobados' => 0,
            'totalReprobados' => 0,
        ];
    }
}
