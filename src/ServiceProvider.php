<?php

namespace Schwartzmj\Img;

use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{

    protected $tags = [
        \Schwartzmj\Img\Tags\Img::class,
    ];

    protected $viewNamespace = 'smj';

    public function bootAddon()
    {
        //
    }
}
