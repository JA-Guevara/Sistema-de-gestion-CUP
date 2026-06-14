<?php

declare(strict_types=1);

namespace App\Inscripcion\Domain\Catalog;

final class ModalidadPostulacion
{
    public const PRESENCIAL = 'PRESENCIAL';
    public const VIRTUAL = 'VIRTUAL';

    private const LABELS = [
        self::PRESENCIAL => 'Presencial',
        self::VIRTUAL => 'Virtual',
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::LABELS);
    }

    public static function label(string $modalidad): string
    {
        return self::LABELS[$modalidad] ?? $modalidad;
    }

    public static function isValid(string $modalidad): bool
    {
        return in_array($modalidad, self::all(), true);
    }
}
