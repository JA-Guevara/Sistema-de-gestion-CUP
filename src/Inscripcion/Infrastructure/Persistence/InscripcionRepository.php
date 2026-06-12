<?php

declare(strict_types=1);

namespace App\Inscripcion\Infrastructure\Persistence;

use App\Inscripcion\Domain\Entity\Inscripcion;
use Doctrine\ORM\EntityManagerInterface;

final readonly class InscripcionRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Inscripcion $inscripcion): void
    {
        $this->entityManager->persist($inscripcion);
        $this->entityManager->flush();
    }

    public function remove(Inscripcion $inscripcion): void
    {
        $this->entityManager->remove($inscripcion);
        $this->entityManager->flush();
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }

    public function findById(int $id): ?Inscripcion
    {
        return $this->entityManager->find(Inscripcion::class, $id);
    }

    public function findByUserAndGestion(int $userId, int $gestionId): ?Inscripcion
    {
        return $this->entityManager
            ->getRepository(Inscripcion::class)
            ->findOneBy(['user' => $userId, 'gestion' => $gestionId]);
    }

    public function findByCi(string $ci): ?Inscripcion
    {
        return $this->entityManager
            ->getRepository(Inscripcion::class)
            ->findOneBy(['ci' => trim($ci)]);
    }

    /** @return list<Inscripcion> */
    public function listByGestion(int $gestionId): array
    {
        return $this->entityManager
            ->getRepository(Inscripcion::class)
            ->findBy(['gestion' => $gestionId], ['createdAt' => 'DESC']);
    }

    public function countByGestion(int $gestionId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(Inscripcion::class, 'i')
            ->where('i.gestion = :gestionId')
            ->setParameter('gestionId', $gestionId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<Inscripcion> */
    public function search(string $term, int $gestionId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('i')
            ->from(Inscripcion::class, 'i')
            ->where('i.gestion = :gestionId')
            ->andWhere('(LOWER(i.ci) LIKE LOWER(:term) OR LOWER(i.nombres) LIKE LOWER(:term) OR LOWER(i.apellidos) LIKE LOWER(:term) OR LOWER(i.email) LIKE LOWER(:term))')
            ->setParameter('gestionId', $gestionId)
            ->setParameter('term', '%' . trim($term) . '%')
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
