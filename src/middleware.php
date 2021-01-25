<?php

declare(strict_types=1);

use Lits\Config\FrameworkConfig;
use Lits\Framework;
use Middlewares\Whoops;
use PSR7Sessions\Storageless\Http\SessionMiddleware;
use Slim\Middleware\ErrorMiddleware;

return function (Framework $framework): void {
    $framework->app()->add(SessionMiddleware::class);
    $framework->app()->addRoutingMiddleware();

    $settings = $framework->settings();
    assert($settings['framework'] instanceof FrameworkConfig);

    if ((bool) $settings['framework']->debug) {
        $framework->app()->add(Whoops::class);
    }

    $framework->app()->add(ErrorMiddleware::class);
};
