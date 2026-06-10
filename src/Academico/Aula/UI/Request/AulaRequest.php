<?php

declare(strict_types=1);

namespace App\Academico\Aula\UI\Request;

use App\Academico\Aula\Application\DTO\AulaInput;
use App\Academico\Aula\Application\DTO\AulasBulkStateInput;
use App\Academico\Aula\Application\DTO\AulasMasivasInput;
use Symfony\Component\HttpFoundation\Request;

final class AulaRequest
{
    public static function fromRequest(Request $request): AulaInput
    {
        return new AulaInput(
            trim((string) $request->request->get('codigo', '')),
            trim((string) $request->request->get('nombre', '')),
            (int) $request->request->get('piso', 0),
            (int) $request->request->get('capacidad', 0),
            self::nullableString($request->request->get('ubicacion')),
            self::actorUserId($request),
        );
    }

    public static function massiveFromRequest(Request $request): AulasMasivasInput
    {
        return new AulasMasivasInput(
            prefijoCodigo: trim((string) $request->request->get('prefijoCodigo', '')),
            piso: (int) $request->request->get('piso', 0),
            numeroInicio: (int) $request->request->get('numeroInicio', 0),
            numeroFin: (int) $request->request->get('numeroFin', 0),
            capacidad: (int) $request->request->get('capacidad', 0),
            ubicacion: self::nullableString($request->request->get('ubicacion')),
            actorUserId: self::actorUserId($request),
        );
    }

    public static function bulkStateFromRequest(Request $request): AulasBulkStateInput
    {
        return new AulasBulkStateInput(
            aulaIds: self::intList($request->request->all('ids')),
            accion: mb_strtoupper(trim((string) $request->request->get('accion', ''))),
            actorUserId: self::actorUserId($request),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
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

    public static function actorUserId(Request $request): ?int
    {
        $userId = $request->getSession()->get('auth_user_id');

        return is_int($userId) ? $userId : null;
    }
}
