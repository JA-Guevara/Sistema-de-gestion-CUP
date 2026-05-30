<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Persistence;

use App\Auth\Entity\PasswordResetToken;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PasswordResetTokenRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(PasswordResetToken $token): void
    {
        $this->entityManager->persist($token);
        $this->entityManager->flush();
    }

    public function findByToken(string $token): ?PasswordResetToken
    {
        return $this->entityManager
            ->getRepository(PasswordResetToken::class)
            ->findOneBy(['token' => $token]);
    }

    /**
     * Invalida cualquier token previo del usuario que aún no haya sido usado.
     * Útil al emitir uno nuevo para que el viejo deje de servir.
     */
    public function markAllUnusedAsUsedFor(int $userId): void
    {
        $now = new \DateTimeImmutable();
        $this->entityManager
            ->createQuery(
                'UPDATE App\\Auth\\Entity\\PasswordResetToken t
                 SET t.usedAt = :now
                 WHERE t.userId = :userId AND t.usedAt IS NULL'
            )
            ->setParameter('now', $now)
            ->setParameter('userId', $userId)
            ->execute();
    }
}
