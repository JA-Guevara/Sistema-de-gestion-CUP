<?php

declare(strict_types=1);

namespace App\Gestion\UI\Twig;

use App\Gestion\Domain\Entity\Gestion;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Nomenclatura unificada de gestiones para TODO el sistema (combos, tablas,
 * reportes, PDF, Excel): convierte el codigo almacenado (p.ej. "CUP-2026-I")
 * al formato legible y consistente "CUP 1-2026".
 *
 * Uso en Twig: {{ gestion|gestion_label }}  o  {{ gestion.codigo|gestion_label }}
 */
final class GestionExtension extends AbstractExtension
{
    private const ROMANOS = ['I' => 1, 'II' => 2, 'III' => 3, 'IV' => 4, 'V' => 5, 'VI' => 6];

    public function getFilters(): array
    {
        return [
            new TwigFilter('gestion_label', $this->label(...)),
        ];
    }

    public function label(Gestion|string|null $gestion): string
    {
        if ($gestion === null) {
            return 'Sin gestion';
        }

        $codigo = trim($gestion instanceof Gestion ? $gestion->codigo : (string) $gestion);
        if ($codigo === '') {
            return 'Sin gestion';
        }

        // PREFIJO - AAAA - ROMANO (p.ej. CUP-2026-I, "CUP 2026 II", etc.)
        if (preg_match('/^([A-Za-z]+)[\s\-]+(\d{4})[\s\-]+([IVX]+)$/i', $codigo, $m) === 1) {
            $periodo = self::ROMANOS[mb_strtoupper($m[3])] ?? 1;

            return sprintf('%s %d-%s', mb_strtoupper($m[1]), $periodo, $m[2]);
        }

        // Variante AAAA-N o "CUP AAAA-N" ya en numero.
        if (preg_match('/^([A-Za-z]*)[\s\-]*(\d{4})[\s\-]+(\d)$/i', $codigo, $m) === 1) {
            $prefijo = $m[1] !== '' ? mb_strtoupper($m[1]) : 'CUP';

            return sprintf('%s %d-%s', $prefijo, (int) $m[3], $m[2]);
        }

        return $codigo;
    }
}
