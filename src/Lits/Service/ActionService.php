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
    public ServerRequest $request;
    public Response $response;
    public RouteCollector $routeCollector;
    public Settings $settings;
    public Logger $logger;
    public Session $session;
    public Template $template;

    public function __construct(
        ServerRequest $request,
        RouteCollector $routeCollector,
        Response $response,
        Settings $settings,
        Logger $logger,
        Session $session,
        Template $template
    ) {
        $this->request = $request;
        $this->response = $response;
        $this->routeCollector = $routeCollector;
        $this->settings = $settings;
        $this->logger = $logger;
        $this->session = $session;
        $this->template = $template;
    }
}
