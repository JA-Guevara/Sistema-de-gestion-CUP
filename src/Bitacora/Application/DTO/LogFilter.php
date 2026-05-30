<?php

declare(strict_types=1);

namespace App\Bitacora\Application\DTO;

/**
 * Criterios de búsqueda para listar la bitácora.
 * Todos los campos son opcionales. La página por defecto es 1, 25 por página.
 */
final readonly class LogFilter
{
    public function __construct(
        public ?string $userLabel = null,
        public ?string $action = null,
        public ?string $module = null,
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
        public int $page = 1,
        public int $perPage = 25,
    ) {
    }
}
