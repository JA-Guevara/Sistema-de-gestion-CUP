<?php

declare(strict_types=1);

use App\Auth\Infrastructure\Security\CsrfManager;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

require __DIR__.'/vendor/autoload.php';

(new Dotenv())->bootEnv(__DIR__.'/.env');

$kernel = new App\Kernel('dev', true);
$kernel->boot();

$uid = 1; // usuario real confirmado por DQL
$sid = 'bbd00c2dcfc12708b9d0cd52dd8e72ab'; // user.currentSessionId de id=1 (para pasar SessionGuard)

$makeSession = static function (int $uid, string $sid): Session {
    $s = new Session(new MockArraySessionStorage());
    $s->setId($sid);
    $s->start();
    $s->set('auth_user_id', $uid);
    // auth_last_activity sin definir -> SessionGuard omite el chequeo de timeout.

    return $s;
};

// ---- Check A: POST autenticado SIN token CSRF -> debe rechazarse (sin ejecutar el caso de uso) ----
$reqA = Request::create('/inscripcion/pagar/999999', 'POST');
$sessA = $makeSession($uid);
$reqA->setSession($sessA);
$respA = $kernel->handle($reqA);
$flashA = $sessA->getFlashBag()->peek('error');
$locA = (string) $respA->headers->get('Location');
$kernel->terminate($reqA, $respA);
echo "A) status={$respA->getStatusCode()} location={$locA}\n";
echo 'A) flash error: '.($flashA[0] ?? '(ninguno)')."\n";
$aOk = $respA->getStatusCode() === 302
    && str_contains($locA, '/inscripcion/ver/999999')
    && !empty($flashA);
echo 'A) RECHAZADO sin token: '.($aOk ? 'PASS' : 'FAIL')."\n\n";

// ---- Check B: GET autenticado emite token en sesion ----
$reqB = Request::create('/inscripcion', 'GET');
$sessB = $makeSession($uid);
$reqB->setSession($sessB);
$respB = $kernel->handle($reqB);
$tokenB = (string) $sessB->get('csrf.inscripcion', '');
$kernel->terminate($reqB, $respB);
echo "B) status={$respB->getStatusCode()} token_en_sesion=".($tokenB !== '' ? substr($tokenB, 0, 12).'…' : '(vacio)')."\n";
$bOk = $respB->getStatusCode() === 200 && $tokenB !== '';
echo 'B) GET emite token: '.($bOk ? 'PASS' : 'FAIL')."\n\n";

// ---- Check C: CsrfManager issue()+validate() (camino de aceptacion y de rechazo) ----
$stack = new RequestStack();
$reqC = new Request();
$reqC->setSession($makeSession($uid));
$stack->push($reqC);
$csrf = new CsrfManager($stack);
$tok = $csrf->issue('inscripcion');
$accept = $csrf->validate('inscripcion', $tok);          // token correcto -> true (consume)
$reject = $csrf->validate('inscripcion', 'token-falso'); // ya consumido / incorrecto -> false
echo 'C) validate(correcto)='.var_export($accept, true).' validate(falso)='.var_export($reject, true)."\n";
$cOk = $accept === true && $reject === false;
echo 'C) CsrfManager round-trip: '.($cOk ? 'PASS' : 'FAIL')."\n\n";

echo 'RESULTADO: '.(($aOk && $bOk && $cOk) ? 'TODO PASS' : 'HAY FALLOS')."\n";

$kernel->shutdown();
