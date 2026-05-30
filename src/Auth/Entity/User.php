<?php

namespace App\Auth\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    public string $email;

    #[ORM\Column(length: 80)]
    public string $firstName;

    #[ORM\Column(length: 80)]
    public string $lastName;

    #[ORM\Column]
    public string $passwordHash;

    /** ID de la sesión activa (single-session enforcement). */
    #[ORM\Column(length: 128, nullable: true)]
    public ?string $currentSessionId = null;

    /** Timestamp del último login exitoso. */
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $lastLoginAt = null;

    /**
     * Contador de intentos fallidos consecutivos de login.
     * Se resetea a 0 con un login exitoso.
     */
    #[ORM\Column(options: ['default' => 0])]
    public int $failedLoginAttempts = 0;

    /** Cuenta bloqueada por superar el umbral de intentos fallidos. */
    #[ORM\Column(options: ['default' => false])]
    public bool $locked = false;

    /** Código de 6 dígitos para desbloquear la cuenta. */
    #[ORM\Column(length: 6, nullable: true)]
    public ?string $unlockCode = null;

    /** Cuándo expira el código de desbloqueo. */
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $unlockCodeExpiresAt = null;

    /** Timestamp del último envío del código (para el cooldown de reenvío). */
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $unlockCodeLastSentAt = null;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
