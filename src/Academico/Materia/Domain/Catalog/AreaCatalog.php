<?php

declare(strict_types=1);

namespace App\Academico\Materia\Domain\Catalog;

/**
 * Areas de conocimiento del CUP. Una materia pertenece a un area y un docente
 * declara las areas en las que esta habilitado; al asignar docente a materia se
 * valida que el area de la materia este entre las del docente.
 */
final class AreaCatalog
{
    public const MATEMATICA = 'MATEMATICA';
    public const FISICA = 'FISICA';
    public const INGLES = 'INGLES';
    public const COMPUTACION = 'COMPUTACION';
    public const OTRA = 'OTRA';

    /** @var array<string,string> codigo => etiqueta legible */
    private const LABELS = [
        self::MATEMATICA => 'Matematica',
        self::FISICA => 'Fisica',
        self::INGLES => 'Ingles',
        self::COMPUTACION => 'Computacion',
        self::OTRA => 'Otra',
    ];

    /** @return list<array{code:string,label:string}> */
    public static function all(): array
    {
        $out = [];
        foreach (self::LABELS as $code => $label) {
            $out[] = ['code' => $code, 'label' => $label];
        }

        return $out;
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::LABELS);
    }

    public static function isValid(string $code): bool
    {
        return isset(self::LABELS[$code]);
    }

    /** Normaliza un valor a un codigo valido o null. */
    public static function normalize(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $code = mb_strtoupper(trim($code));

        return self::isValid($code) ? $code : null;
    }

    /**
     * Filtra una lista a solo codigos de area validos (sin duplicados).
     *
     * @param iterable<mixed> $values
     * @return list<string>
     */
    public static function filterValid(iterable $values): array
    {
        $out = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }
            $code = mb_strtoupper(trim($value));
            if (self::isValid($code) && !in_array($code, $out, true)) {
                $out[] = $code;
            }
        }

        return $out;
    }

    public static function label(?string $code): string
    {
        if ($code === null) {
            return '';
        }

        return self::LABELS[$code] ?? $code;
    }
}
