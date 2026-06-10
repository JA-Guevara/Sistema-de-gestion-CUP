<?php

use App\Kernel;

// Zona horaria por defecto fija para toda la app (independiente del php.ini de
// cada maquina). Garantiza que la bitacora y cualquier fecha se lean y muestren
// siempre en hora de Bolivia, igual en local y en el servidor.
date_default_timezone_set('America/La_Paz');

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
