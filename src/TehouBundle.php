<?php

namespace App;

use App\DependencyInjection\TehouExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class TehouBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function getContainerExtension()
    {
        return new TehouExtension();
    }
}
