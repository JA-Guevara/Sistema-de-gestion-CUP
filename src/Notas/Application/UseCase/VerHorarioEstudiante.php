<?php

declare(strict_types=1);

namespace App\Notas\Application\UseCase;

use App\Academico\Horario\Domain\Entity\Horario;
use App\Academico\Horario\Infrastructure\Persistence\HorarioRepository;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Inscripcion\Domain\Catalog\TipoPostulacion;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;
use App\Notas\Infrastructure\Persistence\AsignacionGrupoRepository;

/**
 * Horario del estudiante: las franjas (dia/hora/aula) de los grupos a los que
 * esta asignado en sus materias, ordenadas por dia y hora.
 */
final readonly class VerHorarioEstudiante
{
    private const ORDEN_DIAS = [
        'LUNES' => 1,
        'MARTES' => 2,
        'MIERCOLES' => 3,
        'MIÉRCOLES' => 3,
        'JUEVES' => 4,
        'VIERNES' => 5,
        'SABADO' => 6,
        'SÁBADO' => 6,
        'DOMINGO' => 7,
    ];

    public function __construct(
        private InscripcionRepository $inscripciones,
        private GestionRepository $gestiones,
        private AsignacionGrupoRepository $asignacionesGrupo,
        private HorarioRepository $horarios,
    ) {
    }

    /**
     * @return array{
     *     inscripcion: \App\Inscripcion\Domain\Entity\Inscripcion,
     *     gestion: \App\Gestion\Domain\Entity\Gestion,
     *     franjas: list<Horario>
     * }|null
     */
    public function execute(int $userId): ?array
    {
        $gestion = $this->gestiones->findActive();
        if ($gestion === null) {
            return null;
        }

        $inscripcion = $this->inscripciones->findConfirmadaByUserAndGestion($userId, $gestion->id, TipoPostulacion::ESTUDIANTE);
        if ($inscripcion === null) {
            return null;
        }

        $franjas = [];
        foreach ($this->asignacionesGrupo->listByInscripcion($inscripcion->id) as $asignacion) {
            foreach ($this->horarios->listByGrupoAndMateria((int) $asignacion->grupo->id, (int) $asignacion->materia->id) as $horario) {
                $franjas[] = $horario;
            }
        }

        usort($franjas, static function (Horario $a, Horario $b): int {
            $da = self::ORDEN_DIAS[mb_strtoupper($a->dia)] ?? 99;
            $db = self::ORDEN_DIAS[mb_strtoupper($b->dia)] ?? 99;

            return $da !== $db ? $da <=> $db : $a->horaInicio <=> $b->horaInicio;
        });

        return [
            'inscripcion' => $inscripcion,
            'gestion' => $gestion,
            'franjas' => $franjas,
        ];
    }
}
