<?php

declare(strict_types=1);

namespace App\Usuario\Application\UseCase;

use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Bitacora\Application\UseCase\RecordLogEntry;
use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Catalog\ModuleCatalog;
use App\Usuario\Application\DTO\UsuarioActionInput;
use App\Usuario\Domain\Exception\UsuarioException;

final readonly class ToggleUserState
{
    public function __construct(
        private UserRepository $users,
        private RecordLogEntry $audit,
    ) {
    }

    public function execute(UsuarioActionInput $input): User
    {
        $user = $this->loadUser($input);
        $this->validateCanChangeState($user, $input);
        $this->updateUserState($user);
        $this->saveUser($user);
        $this->registerAudit($user, $input);
        $this->notify($user);

        return $user;
    }

    private function loadUser(UsuarioActionInput $input): User
    {
        $user = $this->users->findById($input->userId);
        if ($user === null) {
            throw new UsuarioException('El usuario solicitado no existe.');
        }

        return $user;
    }

    private function validateCanChangeState(User $user, UsuarioActionInput $input): void
    {
        if ($user->active && $input->actorUserId !== null && $user->id === $input->actorUserId) {
            throw new UsuarioException('No puedes desactivar tu propio usuario.');
        }
    }

    private function updateUserState(User $user): void
    {
        if ($user->active) {
            $user->deactivate();
            return;
        }

        $user->activate();
    }

    private function saveUser(User $user): void
    {
        $this->users->save($user);
    }

    private function registerAudit(User $user, UsuarioActionInput $input): void
    {
        $action = $user->active ? ActionCatalog::ACTIVATE : ActionCatalog::DEACTIVATE;
        $this->audit->execute($action, ModuleCatalog::USUARIOS, sprintf('Se cambio el estado del usuario %s.', $user->email), $input->actorUserId);
    }

    private function notify(User $user): void
    {
        // El cambio queda en bitacora; no se envia correo automatico.
    }
}
