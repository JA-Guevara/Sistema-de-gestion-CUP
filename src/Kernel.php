<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function __construct(string $environment, bool $debug)
    {
        // Zona horaria de Bolivia (UTC-4). PHP corre en UTC por defecto, lo que
        // dejaba la bitacora y los createdAt 4 horas adelantados. Fijarla aqui
        // afecta a TODO `new \DateTimeImmutable()` (escritura, hidratacion de
        // Doctrine y formateo de Twig), en web y en consola.
        date_default_timezone_set('America/La_Paz');

        parent::__construct($environment, $debug);
    }

    public function getCacheDir(): string
    {
        if ($this->isRunningOnVercel()) {
            return sys_get_temp_dir().'/symfony-cache/'.$this->environment;
        }

        return parent::getCacheDir();
    }

    public function getLogDir(): string
    {
        if ($this->isRunningOnVercel()) {
            return sys_get_temp_dir().'/symfony-logs';
        }

        return parent::getLogDir();
    }

    private function isRunningOnVercel(): bool
    {
        return (bool) ($_SERVER['VERCEL'] ?? $_ENV['VERCEL'] ?? getenv('VERCEL'));
    }
}
