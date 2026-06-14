<?php

declare(strict_types=1);

namespace App\Usuario\UI\Request;

use App\Usuario\Application\DTO\RoleActionInput;
use App\Usuario\Application\DTO\RoleInput;
use Symfony\Component\HttpFoundation\Request;

final class RoleRequest
{
    public static function fromRequest(Request $request): RoleInput
    {
        $description = trim((string) $request->request->get('description', ''));

        return new RoleInput(
            name: (string) $request->request->get('name', ''),
            description: $description !== '' ? $description : null,
            active: $request->request->getBoolean('active', false),
            permissionIds: self::idsFromRequest($request, 'permissions'),
            actorUserId: self::actorUserId($request),
        );
    }

    public static function actionFromRequest(Request $request, int $roleId): RoleActionInput
    {
        return new RoleActionInput($roleId, self::actorUserId($request));
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
