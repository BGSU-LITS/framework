<?php

declare(strict_types=1);

namespace Lits;

use GetOpt\ArgumentException;
use GetOpt\GetOpt;
use Lits\Exception\InvalidAccessException;
use Lits\Service\CommandService;
use Psr\Log\LoggerInterface as Logger;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

abstract class Command
{
    protected ServerRequest $request;
    protected Response $response;
    protected Settings $settings;
    protected Logger $logger;
    protected GetOpt $getopt;

    /** @var array<string, string> */
    protected array $data = [];

    abstract protected function command(): void;

    public function __construct(CommandService $service)
    {
        $this->request = $service->request;
        $this->response = $service->response;
        $this->settings = $service->settings;
        $this->logger = $service->logger;
        $this->getopt = $service->getopt;
    }

    final protected function process(): bool
    {
        $this->getopt->set(GetOpt::SETTING_STRICT_OPTIONS, true);
        $this->getopt->set(GetOpt::SETTING_STRICT_OPERANDS, true);

        try {
            $this->getopt->process();
        } catch (ArgumentException $exception) {
            $this->response->getBody()->write(
                $this->getopt->getHelpText()
            );

            return false;
        }

        return true;
    }

    /** @param array<string, string> $data */
    protected function setup(
        ServerRequest $request,
        Response $response,
        array $data
    ): void {
        $this->request = $request;
        $this->response = $response;
        $this->data = $data;

        if (\PHP_SAPI !== 'cli') {
            throw new InvalidAccessException(
                'Command not accessed via CLI.'
            );
        }
    }

    /** @param array<string, string> $data */
    public function __invoke(
        ServerRequest $request,
        Response $response,
        array $data
    ): Response {
        $this->setup($request, $response, $data);
        $this->command();

        return $this->response;
    }
}
