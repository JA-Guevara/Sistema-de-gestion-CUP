<?php

declare(strict_types=1);

namespace App\Notas\UI\Request;

use App\Notas\Application\DTO\AsignacionInput;
use Symfony\Component\HttpFoundation\Request;

final class AsignacionRequest
{
    public static function fromRequest(Request $request): AsignacionInput
    {
        return new AsignacionInput(
            materiaId: (int) $request->request->get('materia', 0),
            grupoId: (int) $request->request->get('grupo', 0),
            docenteId: (int) $request->request->get('docente', 0),
            actorUserId: self::actorUserId($request),
        );
    }

    private static function actorUserId(Request $request): ?int
    {
        $userId = $request->getSession()->get('auth_user_id');

        return is_int($userId) ? $userId : null;
    }
}
