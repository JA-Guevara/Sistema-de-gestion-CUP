<?php

declare(strict_types=1);

namespace App\Asignacion\UI\Request;

use App\Asignacion\Application\DTO\AsignarDocenteGrupoMateriasInput;
use Symfony\Component\HttpFoundation\Request;

final class AsignarDocenteGrupoMateriasRequest
{
    public static function fromRequest(Request $request): AsignarDocenteGrupoMateriasInput
    {
        return new AsignarDocenteGrupoMateriasInput(
            docenteId: (int) $request->request->get('docente', 0),
            grupoId: (int) $request->request->get('grupo', 0),
            materiaIds: array_values(array_map('intval', (array) $request->request->all('materias'))),
            actorUserId: self::actorUserId($request),
        );
    }

    private static function actorUserId(Request $request): ?int
    {
        $userId = $request->getSession()->get('auth_user_id');

        return is_int($userId) ? $userId : null;
    }
}
