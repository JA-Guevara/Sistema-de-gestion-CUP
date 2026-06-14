<?php

declare(strict_types=1);

namespace App\Inscripcion\Infrastructure\Persistence;

use App\Inscripcion\Domain\Entity\CalendarioRevision;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CalendarioRevisionRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(CalendarioRevision $dia): void
    {
        $this->entityManager->persist($dia);
        $this->entityManager->flush();
    }

    /** @param list<CalendarioRevision> $dias */
    public function saveMany(array $dias): void
    {
        foreach ($dias as $dia) {
            $this->entityManager->persist($dia);
        }

        $this->entityManager->flush();
    }

    public function remove(CalendarioRevision $dia): void
    {
        $this->entityManager->remove($dia);
        $this->entityManager->flush();
    }

    public function findById(int $id): ?CalendarioRevision
    {
        return $this->entityManager->find(CalendarioRevision::class, $id);
    }

    /** @return list<CalendarioRevision> */
    public function listByGestion(int $gestionId): array
    {
        return $this->entityManager
            ->getRepository(CalendarioRevision::class)
            ->findBy(['gestion' => $gestionId], ['fecha' => 'ASC']);
    }

    public function findByGestionAndFecha(int $gestionId, \DateTimeImmutable $fecha): ?CalendarioRevision
    {
        return $this->entityManager
            ->getRepository(CalendarioRevision::class)
            ->findOneBy(['gestion' => $gestionId, 'fecha' => $fecha->setTime(0, 0)]);
    }

    /**
     * Primer dia habil con cupo a partir de una fecha (incluida), por gestion.
     * Es el dia que se asigna automaticamente al presentar.
     */
    public function findPrimerDiaDisponible(int $gestionId, \DateTimeImmutable $desde): ?CalendarioRevision
    {
        return $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(CalendarioRevision::class, 'c')
            ->where('c.gestion = :gestion')
            ->andWhere('c.habilitado = :habilitado')
            ->andWhere('c.fecha >= :desde')
            ->andWhere('c.agendados < c.capacidad')
            ->setParameter('gestion', $gestionId)
            ->setParameter('habilitado', true)
            ->setParameter('desde', $desde->setTime(0, 0))
            ->orderBy('c.fecha', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
