<?php

declare(strict_types=1);

namespace App\Academico\Horario\Application\DTO;

/**
 * Entrada de la generacion masiva de horarios para VARIOS grupos a la vez,
 * a partir de un turno del catalogo.
 *
 * Estrategias:
 *  - PARALELO:   todos los grupos cursan las mismas materias en el mismo
 *                horario; cada grupo en su propia aula (sirve cuando hay
 *                docentes/aulas suficientes).
 *  - ESCALONADO: las materias rotan entre grupos (carrusel), de modo que en
 *                cada franja cada materia la dicta un solo grupo; asi un unico
 *                docente por materia puede cubrir varios grupos en bloques
 *                consecutivos. El docente se asigna despues, no aqui.
 *
 * En ambos casos el aula se asigna por grupo (una por grupo).
 */
final readonly class HorarioMultiGrupoInput
{
    public const ESTRATEGIA_PARALELO = 'PARALELO';
    public const ESTRATEGIA_ESCALONADO = 'ESCALONADO';

    /**
     * @param list<string>                            $dias       Dias de la semana.
     * @param list<int>                               $materiaIds Materias en orden.
     * @param list<array{grupoId:int, aulaId:int}>    $grupos     Grupos con su aula.
     */
    public function __construct(
        public int $turnoId,
        public string $estrategia,
        public array $dias,
        public int $duracionMinutos,
        public int $descansoMinutos,
        public array $materiaIds,
        public array $grupos,
        public bool $reemplazar,
        public ?int $actorUserId,
    ) {
    }
}
