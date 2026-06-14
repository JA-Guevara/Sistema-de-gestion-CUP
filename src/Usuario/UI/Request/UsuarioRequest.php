<?php

declare(strict_types=1);

namespace App\Usuario\UI\Request;

use App\Usuario\Application\DTO\UsuarioActionInput;
use App\Usuario\Application\DTO\UsuarioInput;
use Symfony\Component\HttpFoundation\Request;

final class UsuarioRequest
{
    public static function fromRequest(Request $request, bool $passwordRequired): UsuarioInput
    {
        $password = trim((string) $request->request->get('password', ''));

        return new UsuarioInput(
            firstName: (string) $request->request->get('firstName', ''),
            lastName: (string) $request->request->get('lastName', ''),
            email: (string) $request->request->get('email', ''),
            password: $password !== '' || $passwordRequired ? $password : null,
            active: $request->request->getBoolean('active', false),
            roleIds: self::idsFromRequest($request, 'roles'),
            actorUserId: self::actorUserId($request),
        );
    }

    public static function actionFromRequest(Request $request, int $userId): UsuarioActionInput
    {
        return new UsuarioActionInput($userId, self::actorUserId($request));
    }

    public static function actorUserId(Request $request): ?int
    {
        $userId = $request->getSession()->get('auth_user_id');

        return is_int($userId) ? $userId : null;
    }

    /** @return list<int> */
    private static function idsFromRequest(Request $request, string $key): array
    {
        $values = $request->request->all($key);

        return array_values(array_filter(array_map('intval', is_array($values) ? $values : [])));
    }
}
