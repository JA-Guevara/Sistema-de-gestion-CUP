<?php

declare(strict_types=1);

namespace App\Academico\Horario\UI\Request;

use App\Academico\Horario\Application\DTO\HorarioMasivoInput;
use App\Academico\Horario\Application\DTO\HorarioMultiGrupoInput;
use App\Academico\Horario\Application\DTO\HorariosGrupoBulkDeleteInput;
use App\Academico\Horario\Application\DTO\HorariosPorTurnoInput;
use App\Academico\Horario\Application\DTO\HorarioInput;
use Symfony\Component\HttpFoundation\Request;

final class HorarioRequest
{
    public static function fromRequest(Request $request): HorarioInput
    {
        return new HorarioInput(
            (int) $request->request->get('grupoId', 0),
            (int) $request->request->get('materiaId', 0),
            (int) $request->request->get('aulaId', 0),
            trim((string) $request->request->get('dia', '')),
            self::nullableTime($request->request->get('horaInicio')),
            self::nullableTime($request->request->get('horaFin')),
            self::actorUserId($request),
        );
    }

    public static function byTurnRequest(Request $request): HorariosPorTurnoInput
    {
        return new HorariosPorTurnoInput(
            grupoId: (int) $request->request->get('grupoId', 0),
            materiaId: (int) $request->request->get('materiaId', 0),
            aulaId: (int) $request->request->get('aulaId', 0),
            dias: self::stringList($request->request->all('dias')),
            turno: mb_strtoupper(trim((string) $request->request->get('turno', ''))),
            horaInicio: self::nullableTime($request->request->get('horaInicio')),
            horaFin: self::nullableTime($request->request->get('horaFin')),
            actorUserId: self::actorUserId($request),
        );
    }

    public static function masivoRequest(Request $request): HorarioMasivoInput
    {
        return new HorarioMasivoInput(
            grupoId: (int) $request->request->get('grupoId', 0),
            turno: mb_strtoupper(trim((string) $request->request->get('turno', ''))),
            dias: self::stringList($request->request->all('dias')),
            horaInicio: self::nullableTime($request->request->get('horaInicio')),
            horaFin: self::nullableTime($request->request->get('horaFin')),
            duracionMinutos: (int) $request->request->get('duracionMinutos', 0),
            descansoMinutos: (int) $request->request->get('descansoMinutos', 0),
            bloques: self::bloques($request),
            reemplazar: $request->request->get('reemplazar') !== null,
            actorUserId: self::actorUserId($request),
        );
    }

    public static function multiGrupoRequest(Request $request): HorarioMultiGrupoInput
    {
        return new HorarioMultiGrupoInput(
            turnoId: (int) $request->request->get('turnoId', 0),
            estrategia: mb_strtoupper(trim((string) $request->request->get('estrategia', ''))),
            dias: self::stringList($request->request->all('dias')),
            duracionMinutos: (int) $request->request->get('duracionMinutos', 0),
            descansoMinutos: (int) $request->request->get('descansoMinutos', 0),
            materiaIds: self::intList($request->request->all('materiaIds')),
            grupos: self::gruposConAula($request),
            reemplazar: $request->request->get('reemplazar') !== null,
            actorUserId: self::actorUserId($request),
        );
    }

    public static function grupoBulkDeleteRequest(Request $request): HorariosGrupoBulkDeleteInput
    {
        return new HorariosGrupoBulkDeleteInput(
            grupoIds: self::intList($request->request->all('ids')),
            actorUserId: self::actorUserId($request),
        );
    }

    /**
     * Arma la lista de grupos seleccionados con su aula.
     *
     * @return list<array{grupoId:int, aulaId:int}>
     */
    private static function gruposConAula(Request $request): array
    {
        $seleccionados = self::intList($request->request->all('grupos'));

        $grupos = [];
        foreach ($seleccionados as $grupoId) {
            $grupos[] = [
                'grupoId' => $grupoId,
                'aulaId' => (int) $request->request->get(sprintf('aula_grupo_%d', $grupoId), 0),
            ];
        }

        return $grupos;
    }

    /**
     * Arma los bloques (materia + aula + orden) a partir de las materias marcadas.
     *
     * @return list<array{materiaId:int, aulaId:int, orden:int}>
     */
    private static function bloques(Request $request): array
    {
        $seleccionadas = self::intList($request->request->all('incluir'));

        $bloques = [];
        foreach ($seleccionadas as $posicion => $materiaId) {
            $bloques[] = [
                'materiaId' => $materiaId,
                'aulaId' => (int) $request->request->get(sprintf('aula_%d', $materiaId), 0),
                'orden' => (int) $request->request->get(sprintf('orden_%d', $materiaId), $posicion + 1),
            ];
        }

        return $bloques;
    }

    /** @return list<int> */
    private static function intList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $ids = array_map(static fn (mixed $value): int => (int) $value, $values);

        return array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
    }

    /** @return list<string> */
    private static function stringList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn (mixed $value): string => trim((string) $value), $values)));
    }

    private static function nullableTime(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return new \DateTimeImmutable(trim($value));
    }

    private static function actorUserId(Request $request): ?int
    {
        $userId = $request->getSession()->get('auth_user_id');

        return is_int($userId) ? $userId : null;
    }

    public static function actorUserIdFromSession(Request $request): ?int
    {
        return self::actorUserId($request);
    }
}
