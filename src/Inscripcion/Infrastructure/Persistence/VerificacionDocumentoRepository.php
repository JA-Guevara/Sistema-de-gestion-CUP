<?php

declare(strict_types=1);

namespace App\Inscripcion\Infrastructure\Persistence;

use App\Inscripcion\Domain\Entity\VerificacionDocumento;
use Doctrine\ORM\EntityManagerInterface;

final readonly class VerificacionDocumentoRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /** @param list<VerificacionDocumento> $verificaciones */
    public function saveMany(array $verificaciones): void
    {
        foreach ($verificaciones as $verificacion) {
            $this->entityManager->persist($verificacion);
        }

        $this->entityManager->flush();
    }

    public function findByInscripcionAndCodigo(int $inscripcionId, string $codigo): ?VerificacionDocumento
    {
        return $this->entityManager
            ->getRepository(VerificacionDocumento::class)
            ->findOneBy(['inscripcion' => $inscripcionId, 'requisitoCodigo' => $codigo]);
    }
}
