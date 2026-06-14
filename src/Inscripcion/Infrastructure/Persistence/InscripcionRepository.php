<?php

declare(strict_types=1);

namespace App\Inscripcion\Infrastructure\Persistence;

use App\Inscripcion\Domain\Catalog\EstadoInscripcion;
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

    /**
     * @param list<int> $ids
     * @return list<Inscripcion>
     */
    public function findByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }

        return $this->entityManager
            ->getRepository(Inscripcion::class)
            ->createQueryBuilder('i')
            ->where('i.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @param list<Inscripcion> $inscripciones */
    public function saveMany(array $inscripciones): void
    {
        foreach ($inscripciones as $inscripcion) {
            $this->entityManager->persist($inscripcion);
        }

        $this->entityManager->flush();
    }

    /**
     * Postulaciones de una gestion filtradas por tipo y estado. Se usa para
     * listar a los estudiantes CONFIRMADOS candidatos a asignar a grupos.
     *
     * @return list<Inscripcion>
     */
    public function listByGestionTipoEstado(int $gestionId, string $tipo, string $estado): array
    {
        return $this->entityManager
            ->getRepository(Inscripcion::class)
            ->findBy(
                ['gestion' => $gestionId, 'tipo' => $tipo, 'estado' => $estado],
                ['apellidos' => 'ASC', 'nombres' => 'ASC'],
            );
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

    /** @return list<Inscripcion> */
    public function listByUserAndGestion(int $userId, int $gestionId): array
    {
        return $this->entityManager
            ->getRepository(Inscripcion::class)
            ->findBy(['user' => $userId, 'gestion' => $gestionId], ['createdAt' => 'DESC']);
    }

    public function findConfirmadaByUserAndGestion(int $userId, int $gestionId, string $tipo): ?Inscripcion
    {
        return $this->entityManager
            ->getRepository(Inscripcion::class)
            ->findOneBy([
                'user' => $userId,
                'gestion' => $gestionId,
                'tipo' => $tipo,
                'estado' => EstadoInscripcion::CONFIRMADA,
            ]);
    }

    /** Una persona (CI) solo puede tener una postulacion CONFIRMADA por tipo y gestion. */
    public function existeConfirmadaPorCiTipo(string $ci, string $tipo, int $gestionId): bool
    {
        return $this->entityManager
            ->getRepository(Inscripcion::class)
            ->findOneBy([
                'ci' => trim($ci),
                'tipo' => $tipo,
                'gestion' => $gestionId,
                'estado' => EstadoInscripcion::CONFIRMADA,
            ]) !== null;
    }

    /**
     * ¿El usuario tiene OTRA postulacion CONFIRMADA del mismo tipo (excluyendo
     * una)? Se usa al anular para no retirar el rol si sigue confirmado en otra
     * gestion.
     */
    public function existeOtraConfirmadaPorUserTipo(int $userId, string $tipo, int $excludeInscripcionId): bool
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(Inscripcion::class, 'i')
            ->where('i.user = :userId')
            ->andWhere('i.tipo = :tipo')
            ->andWhere('i.estado = :estado')
            ->andWhere('i.id != :excludeId')
            ->setParameter('userId', $userId)
            ->setParameter('tipo', $tipo)
            ->setParameter('estado', EstadoInscripcion::CONFIRMADA)
            ->setParameter('excludeId', $excludeInscripcionId)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
