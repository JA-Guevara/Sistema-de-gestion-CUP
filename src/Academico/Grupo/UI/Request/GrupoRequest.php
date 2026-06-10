<?php

declare(strict_types=1);

namespace App\Academico\Grupo\UI\Request;

use App\Academico\Grupo\Application\DTO\GenerateGruposInput;
use App\Academico\Grupo\Application\DTO\GruposBulkStateInput;
use App\Academico\Grupo\Application\DTO\GrupoInput;
use Symfony\Component\HttpFoundation\Request;

final class GrupoRequest
{
    public static function fromRequest(Request $request): GrupoInput
    {
        return new GrupoInput(
            (int) $request->request->get('gestionId', 0),
            trim((string) $request->request->get('codigo', '')),
            trim((string) $request->request->get('nombre', '')),
            (int) $request->request->get('cupo', 0),
            (int) $request->request->get('inscritosEstimados', 0),
            self::actorUserId($request),
        );
    }

    public static function generateFromRequest(Request $request): GenerateGruposInput
    {
        return new GenerateGruposInput(
            (int) $request->request->get('gestionId', 0),
            (int) $request->request->get('totalInscritos', 0),
            self::actorUserId($request),
        );
    }

    public static function bulkStateFromRequest(Request $request): GruposBulkStateInput
    {
        return new GruposBulkStateInput(
            grupoIds: self::intList($request->request->all('ids')),
            accion: mb_strtoupper(trim((string) $request->request->get('accion', ''))),
            actorUserId: self::actorUserId($request),
        );
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
