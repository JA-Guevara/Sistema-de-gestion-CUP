<?php

declare(strict_types=1);

namespace App\Academico\Carrera\UI\Request;

use App\Academico\Carrera\Application\DTO\CarreraInput;
use Symfony\Component\HttpFoundation\Request;

final class CarreraRequest
{
    public static function fromRequest(Request $request): CarreraInput
    {
        return new CarreraInput(
            codigo: trim((string) $request->request->get('codigo', '')),
            nombre: trim((string) $request->request->get('nombre', '')),
            descripcion: self::nullableString($request->request->get('descripcion')),
            facultad: self::nullableString($request->request->get('facultad')),
            modalidad: self::nullableString($request->request->get('modalidad')),
            actorUserId: self::actorUserId($request),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function actorUserId(Request $request): ?int
    {
        $userId = $request->getSession()->get('auth_user_id');

        return is_int($userId) ? $userId : null;
    }
}
