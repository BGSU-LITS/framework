<?php

declare(strict_types=1);

namespace Lits\ErrorHandler;

use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Log\LoggerInterface as Logger;
use Slim\Handlers\ErrorHandler;
use Slim\Interfaces\CallableResolverInterface as CallableResolver;
use Throwable;

/** @psalm-suppress PropertyNotSetInConstructor */
final class FrameworkErrorHandler extends ErrorHandler
{
    public function __construct(
        CallableResolver $callableResolver,
        ResponseFactory $responseFactory,
        Logger $logger
    ) {
        parent::__construct($callableResolver, $responseFactory, $logger);

        $this->logErrorRenderer = fn (
            Throwable $exception,
            bool $displayErrorDetails
        ): string => (string) $exception;
    }

    protected function writeToErrorLog(): void
    {
        $renderer = $this->callableResolver->resolve($this->logErrorRenderer);
        $error = (string) $renderer($this->exception, $this->logErrorDetails);
        $this->logError($error);
    }
}
