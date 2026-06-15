<?php

declare(strict_types=1);

namespace App\Asignacion\UI\Request;

use App\Asignacion\Application\DTO\AsignarEstudianteGrupoInput;
use Symfony\Component\HttpFoundation\Request;

final class AsignarEstudianteGrupoRequest
{
    public static function fromRequest(Request $request): AsignarEstudianteGrupoInput
    {
        return new AsignarEstudianteGrupoInput(
            grupoId: (int) $request->request->get('grupo', 0),
            inscripcionIds: array_values(array_map('intval', (array) $request->request->all('inscritos'))),
            actorUserId: self::actorUserId($request),
        );
    }

    private static function actorUserId(Request $request): ?int
    {
        $userId = $request->getSession()->get('auth_user_id');

        return is_int($userId) ? $userId : null;
    }
}
