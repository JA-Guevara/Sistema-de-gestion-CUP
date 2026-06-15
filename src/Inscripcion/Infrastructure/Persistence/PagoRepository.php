<?php

declare(strict_types=1);

namespace App\Inscripcion\Infrastructure\Persistence;

use App\Inscripcion\Domain\Catalog\EstadoInscripcion;
use App\Inscripcion\Domain\Catalog\EstadoPago;
use App\Inscripcion\Domain\Entity\Pago;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PagoRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Pago $pago): void
    {
        $this->entityManager->persist($pago);
        $this->entityManager->flush();
    }

    public function findById(int $id): ?Pago
    {
        return $this->entityManager->find(Pago::class, $id);
    }

    public function findByStripeSessionId(string $sessionId): ?Pago
    {
        return $this->entityManager
            ->getRepository(Pago::class)
            ->findOneBy(['stripeSessionId' => $sessionId]);
    }

    /** Pago PAGADO mas reciente de una inscripcion (null si nunca se pago). */
    public function findPagadoByInscripcion(int $inscripcionId): ?Pago
    {
        return $this->entityManager
            ->getRepository(Pago::class)
            ->createQueryBuilder('p')
            ->where('p.inscripcion = :ins')
            ->andWhere('p.estado = :estado')
            ->setParameter('ins', $inscripcionId)
            ->setParameter('estado', EstadoPago::PAGADO)
            ->orderBy('p.paidAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Pagos de una gestion (via inscripcion), opcionalmente filtrados por estado.
     *
     * @return list<Pago>
     */
    public function listByGestion(int $gestionId, ?string $estado = null): array
    {
        $qb = $this->entityManager
            ->getRepository(Pago::class)
            ->createQueryBuilder('p')
            ->join('p.inscripcion', 'i')
            ->addSelect('i')
            ->where('i.gestion = :gestion')
            ->setParameter('gestion', $gestionId)
            ->orderBy('p.createdAt', 'DESC');

        if ($estado !== null && $estado !== '') {
            $qb->andWhere('p.estado = :estado')->setParameter('estado', $estado);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Totales por estado para una gestion (resumen del panel admin).
     *
     * @return array{recaudado:int, pagados:int, pendientes:int}
     */
    public function resumenGestion(int $gestionId): array
    {
        $rows = $this->entityManager
            ->getRepository(Pago::class)
            ->createQueryBuilder('p')
            ->select('p.estado AS estado', 'COUNT(p.id) AS total', 'SUM(p.amount) AS monto')
            ->join('p.inscripcion', 'i')
            ->where('i.gestion = :gestion')
            // El recaudado/resumen NO debe contar pagos de inscripciones que
            // luego fueron anuladas o rechazadas (esos no son recaudacion valida).
            ->andWhere('i.estado NOT IN (:excluidos)')
            ->setParameter('gestion', $gestionId)
            ->setParameter('excluidos', [EstadoInscripcion::ANULADA, EstadoInscripcion::RECHAZADA])
            ->groupBy('p.estado')
            ->getQuery()
            ->getResult();

        $recaudado = 0;
        $pagados = 0;
        $pendientes = 0;
        foreach ($rows as $row) {
            if ($row['estado'] === EstadoPago::PAGADO) {
                $recaudado += (int) $row['monto'];
                $pagados += (int) $row['total'];
            } elseif ($row['estado'] === EstadoPago::PENDIENTE) {
                $pendientes += (int) $row['total'];
            }
        }

        return ['recaudado' => $recaudado, 'pagados' => $pagados, 'pendientes' => $pendientes];
    }
}
