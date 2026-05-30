<?php

declare(strict_types=1);

namespace App\Auth\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Token de un solo uso para recuperar contraseña.
 *
 * El usuario solicita reset → se crea un token con expiración a 30 min →
 * se envía por email → el usuario clickea el link → el token se valida y
 * se marca como usado (uno por vez, no reciclable).
 */
#[ORM\Entity]
#[ORM\Table(name: 'password_reset_tokens')]
class PasswordResetToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column]
    public int $userId;

    #[ORM\Column(length: 64, unique: true)]
    public string $token;

    #[ORM\Column]
    public \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $usedAt = null;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function isUsed(): bool
    {
        return $this->usedAt !== null;
    }
}
