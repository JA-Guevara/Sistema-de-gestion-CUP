<?php

declare(strict_types=1);

namespace App\Gestion\Application\DTO;

/**
 * @param list<PeriodoGestionInput> $periodos
 * @param list<CarreraGestionInput> $carreras
 */
final readonly class GestionInput
{
    public function __construct(
        public string $codigo,
        public string $nombre,
        public ?string $descripcion,
        public int $cupoTotal,
        public int $maxEstudiantesPorGrupo,
        public int $maxGruposPorDocente,
        public int $notaMinimaAprobacion,
        public int $cantidadExamenes,
        public bool $permiteReinscripcion,
        public bool $permiteCambioGrupo,
        public bool $generarBitacora,
        public array $periodos,
        public array $carreras,
        public ?int $actorUserId,
    ) {
    }
}
