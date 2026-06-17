<?php

declare(strict_types=1);

namespace App\Dashboard\Application\UseCase;

use App\Gestion\Domain\Entity\Gestion;

/**
 * Reporte COMPARATIVO multi-gestion: corre el reporte de cada gestion (reusa
 * GetReportes, sin duplicar logica) y extrae sus KPIs clave para ponerlas lado
 * a lado. Pensado para alimentar la pestaña "Comparativo" del Dashboard.
 */
final readonly class GetReportesComparativo
{
    public function __construct(private GetReportes $reportes)
    {
    }

    /**
     * @param list<Gestion> $gestiones
     * @return list<array<string, mixed>>
     */
    public function execute(array $gestiones): array
    {
        $out = [];
        foreach ($gestiones as $gestion) {
            $k = $this->reportes->execute($gestion)['kpis'];
            $out[] = [
                'id' => (int) $gestion->id,
                'codigo' => $gestion->codigo,
                'activa' => $gestion->isActive(),
                'inscritos' => (int) $k['inscritos'],
                'evaluados' => (int) $k['evaluados'],
                'aprobados' => (int) $k['aprobados'],
                'reprobados' => (int) $k['reprobados'],
                'incompletos' => (int) $k['incompletos'],
                'pctAprobacion' => (float) $k['pctAprobacion'],
                'promedioGeneral' => (int) $k['promedioGeneral'],
                'docentes' => (int) $k['docentes'],
                'postulacionesDocentes' => (int) $k['postulacionesDocentes'],
                'docentesAprobados' => (int) $k['docentesAprobados'],
            ];
        }

        return $out;
    }
}
