<?php

declare(strict_types=1);

namespace App\Bitacora\UI\Request;

use App\Bitacora\Application\DTO\LogFilter;
use Symfony\Component\HttpFoundation\Request;

/**
 * Adapta los query params del Request HTTP a un LogFilter del dominio.
 */
final class BitacoraFilterRequest
{
    public static function fromRequest(Request $request): LogFilter
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = max(1, min(100, (int) $request->query->get('per_page', 25)));

        return new LogFilter(
            userLabel: self::nullableString($request->query->get('usuario')),
            action: self::nullableString($request->query->get('accion')),
            module: self::nullableString($request->query->get('modulo')),
            from: self::nullableDate($request->query->get('desde')),
            to: self::nullableDate($request->query->get('hasta')),
            page: $page,
            perPage: $perPage,
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function nullableDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable(trim($value));
        } catch (\Exception) {
            return null;
        }
    }
}
