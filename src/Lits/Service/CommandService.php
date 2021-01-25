<?php

declare(strict_types=1);

namespace Lits\Service;

use GetOpt\GetOpt;
use Lits\Settings;
use Psr\Log\LoggerInterface as Logger;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final class CommandService
{
    public ServerRequest $request;
    public Response $response;
    public Settings $settings;
    public Logger $logger;
    public GetOpt $getopt;

    public function __construct(
        ServerRequest $request,
        Response $response,
        Settings $settings,
        Logger $logger,
        GetOpt $getopt
    ) {
        $this->request = $request;
        $this->response = $response;
        $this->settings = $settings;
        $this->logger = $logger;
        $this->getopt = $getopt;
    }
}
