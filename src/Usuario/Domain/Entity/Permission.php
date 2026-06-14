<?php

declare(strict_types=1);

namespace App\Usuario\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'permissions')]
#[ORM\UniqueConstraint(name: 'uniq_permissions_code', columns: ['code'])]
class Permission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 120)]
    public string $code;

    #[ORM\Column(length: 80)]
    public string $module;

    #[ORM\Column(length: 80)]
    public string $action;

    #[ORM\Column(length: 180)]
    public string $description;

    #[ORM\Column(options: ['default' => true])]
    public bool $active = true;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('America/La_Paz'));
    }

    public function configure(string $code, string $module, string $action, string $description): void
    {
        $this->code = trim($code);
        $this->module = trim($module);
        $this->action = trim($action);
        $this->description = trim($description);
    }
}
