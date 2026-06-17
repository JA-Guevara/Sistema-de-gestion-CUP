<?php

declare(strict_types=1);

namespace App\Academico\Materia\UI\Request;

use App\Academico\Materia\Application\DTO\MateriaInput;
use App\Academico\Materia\Domain\Catalog\AreaCatalog;
use Symfony\Component\HttpFoundation\Request;

final class MateriaRequest
{
    public static function fromRequest(Request $request): MateriaInput
    {
        return new MateriaInput(
            trim((string) $request->request->get('codigo', '')),
            trim((string) $request->request->get('nombre', '')),
            self::nullableString($request->request->get('descripcion')),
            AreaCatalog::normalize(self::nullableString($request->request->get('area'))),
            self::actorUserId($request),
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
