<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Security;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Gestor mínimo de tokens CSRF basado en sesión.
 *
 * Cada formulario declara una "intención" (auth_login, auth_register, ...).
 * - issue($intention): genera un token, lo guarda en sesión y lo devuelve.
 * - validate($intention, $submitted): compara con el guardado y lo borra (uso único).
 *
 * Comparación con hash_equals para evitar timing attacks.
 */
final readonly class CsrfManager
{
    public function __construct(private RequestStack $requests)
    {
    }

    public function issue(string $intention): string
    {
        $token = bin2hex(random_bytes(32));
        $this->session()->set($this->key($intention), $token);

        return $token;
    }

    public function validate(string $intention, string $submitted): bool
    {
        $session = $this->session();
        $key = $this->key($intention);
        $expected = (string) $session->get($key, '');
        $session->remove($key);

        if ($expected === '' || $submitted === '') {
            return false;
        }

        return hash_equals($expected, $submitted);
    }

    private function session(): SessionInterface
    {
        return $this->requests->getSession();
    }

    private function key(string $intention): string
    {
        return 'csrf.'.$intention;
    }
}
