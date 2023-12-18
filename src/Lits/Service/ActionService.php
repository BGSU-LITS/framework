<?php

declare(strict_types=1);

namespace Lits\Service;

use Lits\Settings;
use Lits\Template;
use PSR7Sessions\Storageless\Session\SessionInterface as Session;
use Psr\Log\LoggerInterface as Logger;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Slim\Interfaces\RouteCollectorInterface as RouteCollector;

final class ActionService
{
    public function __construct(
        public ServerRequest $request,
        public RouteCollector $routeCollector,
        public Response $response,
        public Settings $settings,
        public Logger $logger,
        public Session $session,
        public Template $template,
    ) {
    }
}
