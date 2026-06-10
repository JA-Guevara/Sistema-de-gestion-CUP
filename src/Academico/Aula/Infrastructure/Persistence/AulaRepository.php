<?php

declare(strict_types=1);

namespace App\Academico\Aula\Infrastructure\Persistence;

use App\Academico\Aula\Domain\Entity\Aula;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AulaRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Aula $aula): void
    {
        $this->entityManager->persist($aula);
        $this->entityManager->flush();
    }

    /** @param list<Aula> $aulas */
    public function saveMany(array $aulas): void
    {
        foreach ($aulas as $aula) {
            $this->entityManager->persist($aula);
        }

        $this->entityManager->flush();
    }

    public function findById(int $id): ?Aula
    {
        return $this->entityManager->find(Aula::class, $id);
    }

    /**
     * @param list<int> $ids
     * @return list<Aula>
     */
    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->entityManager->getRepository(Aula::class)->findBy(['id' => array_values(array_unique($ids))]);
    }

    public function findByCodigo(string $codigo): ?Aula
    {
        return $this->entityManager->getRepository(Aula::class)->findOneBy(['codigo' => mb_strtoupper(trim($codigo))]);
    }

    /** @param list<string> $codes @return list<string> */
    public function findExistingCodes(array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        $rows = $this->entityManager->createQueryBuilder()
            ->select('a.codigo')
            ->from(Aula::class, 'a')
            ->where('a.codigo IN (:codes)')
            ->setParameter('codes', array_values(array_unique($codes)))
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): string => (string) $row['codigo'], $rows);
    }

    /** @return list<Aula> */
    public function listAll(): array
    {
        return $this->entityManager->getRepository(Aula::class)->findBy([], ['piso' => 'ASC', 'nombre' => 'ASC']);
    }

    /** @return list<Aula> */
    public function listActive(): array
    {
        return $this->entityManager->getRepository(Aula::class)->findBy(['estado' => Aula::ESTADO_ACTIVA], ['piso' => 'ASC', 'nombre' => 'ASC']);
    }
}
