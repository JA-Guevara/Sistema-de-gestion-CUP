<?php

declare(strict_types=1);

namespace App\Perfil\UI\Controller;

use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Bitacora\Application\EventLog\PerfilEvents;
use App\Perfil\Application\DTO\PasswordChangeInput;
use App\Perfil\Application\DTO\PerfilInput;
use App\Perfil\Application\UseCase\ChangePassword;
use App\Perfil\Application\UseCase\UpdatePerfil;
use App\Perfil\Domain\Exception\PerfilException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/perfil')]
final class PerfilController extends AbstractController
{
    private const SESSION_USER_KEY = 'auth_user_id';
    private const AVATAR_EXTS = ['png', 'jpg', 'jpeg', 'webp'];
    private const MAX_AVATAR_BYTES = 2097152; // 2 MB

    public function __construct(
        private readonly UserRepository $users,
        private readonly UpdatePerfil $updatePerfil,
        private readonly ChangePassword $changePassword,
        private readonly PerfilEvents $events,
    ) {
    }

    #[Route('', name: 'perfil_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->loadAuthenticatedUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        return $this->render('@perfil/perfil.html.twig', [
            'user' => $user,
            'avatarUrl' => $this->avatarUrl($user->id ?? 0),
        ]);
    }

    #[Route('', name: 'perfil_update', methods: ['POST'])]
    public function update(Request $request): Response
    {
        $user = $this->loadAuthenticatedUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        try {
            $this->updatePerfil->execute(new PerfilInput(
                userId: $user->id ?? 0,
                firstName: (string) $request->request->get('firstName', ''),
                lastName: (string) $request->request->get('lastName', ''),
            ));
            $this->addFlash('success', 'Tus datos se actualizaron correctamente.');
        } catch (PerfilException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('perfil_index');
    }

    #[Route('/password', name: 'perfil_password', methods: ['POST'])]
    public function password(Request $request): Response
    {
        $user = $this->loadAuthenticatedUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        try {
            $this->changePassword->execute(new PasswordChangeInput(
                userId: $user->id ?? 0,
                currentPassword: (string) $request->request->get('currentPassword', ''),
                newPassword: (string) $request->request->get('newPassword', ''),
                confirmPassword: (string) $request->request->get('confirmPassword', ''),
            ));
            $this->addFlash('success', 'Tu contraseña se cambió correctamente.');
        } catch (PerfilException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('perfil_index');
    }

    #[Route('/foto', name: 'perfil_foto', methods: ['POST'])]
    public function foto(Request $request): Response
    {
        $user = $this->loadAuthenticatedUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        try {
            $this->guardarFoto($user, $request->files->get('foto'));
            $this->events->fotoActualizada($user->id ?? 0, $user->email);
            $this->addFlash('success', 'Tu foto de perfil se actualizó.');
        } catch (PerfilException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('perfil_index');
    }

    private function guardarFoto(User $user, mixed $file): void
    {
        if (!$file instanceof UploadedFile) {
            throw new PerfilException('No se recibió ninguna imagen.');
        }

        if ($file->getSize() !== null && $file->getSize() > self::MAX_AVATAR_BYTES) {
            throw new PerfilException('La imagen no puede superar los 2 MB.');
        }

        // Evitamos guessExtension()/getMimeType() porque dependen de la extension
        // php_fileinfo (puede no estar habilitada). Usamos lo que envia el navegador.
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext === 'jpe') {
            $ext = 'jpg';
        }
        if ($ext === '') {
            $ext = match (strtolower((string) $file->getClientMimeType())) {
                'image/png' => 'png',
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/webp' => 'webp',
                default => '',
            };
        }
        if (!in_array($ext, self::AVATAR_EXTS, true)) {
            throw new PerfilException('Formato no permitido. Usa PNG, JPG o WEBP.');
        }

        $dir = $this->avatarDir();
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new PerfilException('No se pudo preparar el directorio de imágenes.');
        }

        // Borra cualquier avatar previo del usuario (cualquier extensión).
        foreach (self::AVATAR_EXTS as $previo) {
            $ruta = sprintf('%s/%d.%s', $dir, $user->id ?? 0, $previo);
            if (is_file($ruta)) {
                @unlink($ruta);
            }
        }

        try {
            $file->move($dir, sprintf('%d.%s', $user->id ?? 0, $ext));
        } catch (\Throwable $e) {
            throw new PerfilException('No se pudo guardar la imagen.');
        }
    }

    private function avatarDir(): string
    {
        return rtrim((string) $this->getParameter('kernel.project_dir'), '/\\') . '/public/uploads/avatars';
    }

    private function avatarUrl(int $userId): ?string
    {
        $dir = $this->avatarDir();
        foreach (self::AVATAR_EXTS as $ext) {
            $ruta = sprintf('%s/%d.%s', $dir, $userId, $ext);
            if (is_file($ruta)) {
                return sprintf('/uploads/avatars/%d.%s?v=%d', $userId, $ext, filemtime($ruta) ?: 0);
            }
        }

        return null;
    }

    private function loadAuthenticatedUser(Request $request): ?User
    {
        $userId = $request->getSession()->get(self::SESSION_USER_KEY);

        return is_int($userId) ? $this->users->findById($userId) : null;
    }
}
