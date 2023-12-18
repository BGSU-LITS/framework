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
    public function __construct(
        public ServerRequest $request,
        public Response $response,
        public Settings $settings,
        public Logger $logger,
        public GetOpt $getopt,
    ) {
    }
}
