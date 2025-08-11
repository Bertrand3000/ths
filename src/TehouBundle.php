<?php

namespace App;

use App\DependencyInjection\TehouExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class TehouBundle extends Bundle
{
    public function getContainerExtension()
    {
        return new TehouExtension();
    }
}
