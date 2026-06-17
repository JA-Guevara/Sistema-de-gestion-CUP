<?php

declare(strict_types=1);

namespace App\Dashboard\UI\Controller;

use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Dashboard\Application\UseCase\AsistenteAyuda;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoint del asistente de AYUDA global (chat de onboarding disponible en todas
 * las paginas). Ruta sin prefijo gestionado por PermissionGuard: cualquier usuario
 * autenticado puede preguntar como usar el sistema.
 */
#[Route('/ayuda')]
final class AyudaController extends AbstractController
{
    private const SESSION_USER_KEY = 'auth_user_id';

    public function __construct(
        private readonly AsistenteAyuda $ayuda,
        private readonly UserRepository $users,
    ) {
    }

    #[Route('/asistente', name: 'ayuda_asistente', methods: ['POST'])]
    public function asistente(Request $request): JsonResponse
    {
        $userId = $request->getSession()->get(self::SESSION_USER_KEY);
        $user = is_int($userId) ? $this->users->findById($userId) : null;
        if ($user === null) {
            return new JsonResponse(['ok' => false, 'respuesta' => 'Tu sesión expiró. Iniciá sesión otra vez.'], Response::HTTP_UNAUTHORIZED);
        }

        $consulta = (string) $request->request->get('consulta', '');
        if ($consulta === '' && str_contains((string) $request->headers->get('content-type'), 'application/json')) {
            $body = json_decode($request->getContent(), true);
            $consulta = is_array($body) ? (string) ($body['consulta'] ?? '') : '';
        }

        return new JsonResponse($this->ayuda->execute($consulta, $user->roleNames()));
    }
}
