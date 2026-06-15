<?php

declare(strict_types=1);

namespace App\Asignacion\Application\UseCase;

use App\Academico\Materia\Infrastructure\Persistence\MateriaRepository;
use App\Academico\Turno\Infrastructure\Persistence\TurnoRepository;
use App\Gestion\Domain\Entity\Gestion;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;
use App\Notas\Application\UseCase\ResumenGruposGestion;
use App\Notas\Infrastructure\Persistence\AsignacionDocenteRepository;

/**
 * Datos del panel de Asignaciones centrado en GRUPOS: una tarjeta por grupo de
 * la gestion activa (turno, aula, horario, materias, docentes, cupos) mas los
 * catalogos para los filtros.
 */
final readonly class ShowAsignacionDashboard
{
    public function __construct(
        private GestionRepository $gestiones,
        private ResumenGruposGestion $resumenGrupos,
        private TurnoRepository $turnos,
        private MateriaRepository $materias,
        private AsignacionDocenteRepository $asignacionesDocente,
        private InscripcionRepository $inscripciones,
    ) {
    }

    /** @return array<string, mixed> */
    public function execute(): array
    {
        $gestion = $this->gestiones->findActive();
        $tarjetas = $gestion === null ? [] : $this->resumenGrupos->execute((int) $gestion->id);

        return [
            'gestion' => $gestion,
            'tarjetas' => $tarjetas,
            'turnos' => $this->turnos->listActive(),
            'materias' => $this->materias->listActive(),
            'docentes' => $this->asignacionesDocente->listDocentes(),
            'stats' => $this->stats($gestion, $tarjetas),
        ];
    }

    /**
     * @param list<array<string, mixed>> $tarjetas
     * @return array{inscritos:int, grupos:int, docentes:int, docentesAsignados:int}
     */
    private function stats(?Gestion $gestion, array $tarjetas): array
    {
        $docentesAsignados = 0;
        foreach ($tarjetas as $tarjeta) {
            $docentesAsignados += count($tarjeta['docentes']);
        }

        return [
            'inscritos' => $gestion === null ? 0 : $this->inscripciones->countByGestion((int) $gestion->id),
            'grupos' => count($tarjetas),
            'docentes' => count($this->asignacionesDocente->listDocentes()),
            'docentesAsignados' => $docentesAsignados,
        ];
    }
}
