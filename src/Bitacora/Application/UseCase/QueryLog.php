<?php

declare(strict_types=1);

namespace App\Bitacora\Application\UseCase;

use App\Bitacora\Application\DTO\LogFilter;
use App\Bitacora\Infrastructure\Persistence\LogEntryRepository;

/**
 * Caso de uso: consultar la bitácora aplicando filtros y paginación.
 *
 * Devuelve un array con:
 * - items: array<LogEntry>
 * - total: int (cantidad total que matchea el filtro)
 * - page: int (página actual)
 * - perPage: int
 * - totalPages: int
 */
final readonly class QueryLog
{
    public function __construct(private LogEntryRepository $repository)
    {
    }

    /**
     * @return array{
     *     items: list<\App\Bitacora\Domain\Entity\LogEntry>,
     *     total: int,
     *     page: int,
     *     perPage: int,
     *     totalPages: int
     * }
     */
    public function execute(LogFilter $filter): array
    {
        $total = $this->repository->countByFilter($filter);
        $items = $this->repository->findByFilter($filter);

        $totalPages = (int) max(1, ceil($total / $filter->perPage));

        return [
            'items' => $items,
            'total' => $total,
            'page' => $filter->page,
            'perPage' => $filter->perPage,
            'totalPages' => $totalPages,
        ];
    }
}
