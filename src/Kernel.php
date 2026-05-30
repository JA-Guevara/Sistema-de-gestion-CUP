<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

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
