<?php

declare(strict_types=1);

namespace App\Bitacora\Infrastructure\Persistence;

use App\Bitacora\Application\DTO\LogFilter;
use App\Bitacora\Domain\Entity\LogEntry;
use Doctrine\ORM\EntityManagerInterface;

final readonly class LogEntryRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(LogEntry $entry): void
    {
        $this->entityManager->persist($entry);
        $this->entityManager->flush();
    }

    /** @return list<LogEntry> */
    public function findByFilter(LogFilter $filter): array
    {
        $qb = $this->buildBaseQuery($filter);
        $qb->orderBy('l.createdAt', 'DESC');
        $qb->setFirstResult(($filter->page - 1) * $filter->perPage);
        $qb->setMaxResults($filter->perPage);

        return $qb->getQuery()->getResult();
    }

    public function countByFilter(LogFilter $filter): int
    {
        $qb = $this->buildBaseQuery($filter);
        $qb->select('COUNT(l.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /** Lista los usernames únicos que figuran en la bitácora (para el filtro). */
    public function distinctUserLabels(int $limit = 100): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT l.userLabel')
            ->from(LogEntry::class, 'l')
            ->orderBy('l.userLabel', 'ASC')
            ->setMaxResults($limit);

        return array_column($qb->getQuery()->getScalarResult(), 'userLabel');
    }

    private function buildBaseQuery(LogFilter $filter): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('l')
            ->from(LogEntry::class, 'l');

        if ($filter->userLabel !== null && $filter->userLabel !== '') {
            $qb->andWhere('l.userLabel = :userLabel')->setParameter('userLabel', $filter->userLabel);
        }

        if ($filter->action !== null && $filter->action !== '') {
            $qb->andWhere('l.action = :action')->setParameter('action', $filter->action);
        }

        if ($filter->module !== null && $filter->module !== '') {
            $qb->andWhere('l.module = :module')->setParameter('module', $filter->module);
        }

        if ($filter->from !== null) {
            $qb->andWhere('l.createdAt >= :from')->setParameter('from', $filter->from);
        }

        if ($filter->to !== null) {
            // To incluye todo el día final.
            $toInclusive = $filter->to->setTime(23, 59, 59);
            $qb->andWhere('l.createdAt <= :to')->setParameter('to', $toInclusive);
        }

        return $qb;
    }
}
