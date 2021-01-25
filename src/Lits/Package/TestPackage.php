<?php

declare(strict_types=1);

namespace Lits\Package;

use Lits\Config\FrameworkConfig;
use Lits\Config\SessionConfig;
use Lits\Framework;
use Lits\Package;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as ServerRequest;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

final class TestPackage extends Package
{
    public function definitions(Framework $framework): void
    {
        $framework->addDefinition(TestPackage::class, fn () => true);
    }

    public function middleware(Framework $framework): void
    {
        $framework->app()->add(
            fn (
                ServerRequest $req,
                RequestHandler $reqHandler
            ) => $reqHandler->handle(
                $req->withAttribute('middleware', 'test'),
            )
        );
    }

    public function routes(Framework $framework): void
    {
        $framework->app()->get(
            '/route',
            function (ServerRequest $req, Response $res): Response {
                $res->getBody()->write(
                    (string) $req->getAttribute('middleware'),
                );

                return $res;
            }
        );
    }

    public function settings(Framework $framework): void
    {
        $settings = $framework->settings();

        \assert($settings['framework'] instanceof FrameworkConfig);

        $settings['framework']->proxies[] = '10.0.0.1';

        \assert($settings['session'] instanceof SessionConfig);

        $settings['session']->key =
            'pXK3GNvRgiEmPsEhNNYvzdkZVBiMPHf2fLjiH/2rX7Y=';
    }
}
