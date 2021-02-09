<?php

declare(strict_types=1);

use GetOpt\GetOpt;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lits\Config\FrameworkConfig;
use Lits\Config\SessionConfig;
use Lits\ErrorHandler\FrameworkErrorHandler;
use Lits\Framework;
use Lits\Settings;
use Middlewares\Whoops;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;
use Monolog\Processor\PsrLogMessageProcessor;
use PSR7Sessions\Storageless\Http\SessionMiddleware;
use PSR7Sessions\Storageless\Session\DefaultSessionData;
use PSR7Sessions\Storageless\Session\SessionInterface as Session;
use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ServerRequestInterface as ServerRequest;
use Psr\Log\LoggerInterface as Logger;
use Slim\App;
use Slim\CallableResolver as SlimCallableResolver;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;
use Slim\Http\Response;
use Slim\Interfaces\CallableResolverInterface as CallableResolver;
use Slim\Interfaces\RouteCollectorInterface as RouteCollector;
use Slim\Middleware\ErrorMiddleware;
use Slim\Routing\RouteCollector as SlimRoutingRouteCollector;

return function (Framework $framework): void {
    $framework->addDefinition(
        App::class,
        DI\factory([AppFactory::class, 'createFromContainer']),
    );

    $framework->addDefinition(
        CallableResolver::class,
        DI\autowire(SlimCallableResolver::class)
            ->constructorParameter('container', DI\get(Container::class)),
    );

    $framework->addDefinition(
        ErrorMiddleware::class,
        DI\autowire()
            ->constructorParameter('displayErrorDetails', false)
            ->constructorParameter('logErrors', true)
            ->constructorParameter('logErrorDetails', true)
            ->method(
                'setDefaultErrorHandler',
                DI\get(FrameworkErrorHandler::class),
            ),
    );

    $framework->addDefinition(
        GetOpt::class,
        DI\autowire()->constructorParameter(
            'settings',
            [GetOpt::SETTING_STRICT_OPTIONS => false]
        )
    );

    $framework->addDefinition(
        Logger::class,
        function (Settings $settings): Logger {
            // Create new Monolog logger.
            $logger = new MonologLogger('lits');

            // Create error_log handler and formatter to use by default.
            $handler = new ErrorLogHandler();

            // Error log includes datetime by default, so just log details.
            $format = "%channel%.%level_name%: %message%\n";
            $formatter = new LineFormatter($format, null, true);

            // If log path is specified, create a stream handler and formatter.
            assert($settings['framework'] instanceof FrameworkConfig);

            if (!is_null($settings['framework']->log)) {
                $handler = new StreamHandler($settings['framework']->log);

                // Include the datetime in a similar format for error_log.
                $formatter = new LineFormatter(
                    '[%datetime%] ' . $format,
                    'd-M-Y H:i:s e',
                    true,
                );
            }

            // Add formatter to handler and add the handler to the logger.
            $handler->setFormatter($formatter);
            $logger->pushHandler($handler);

            // Add the PSR log message processor to the logger.
            $logger->pushProcessor(new PsrLogMessageProcessor());

            return $logger;
        },
    );

    $framework->addDefinition(
        Response::class,
        fn (ResponseFactory $factory) => $factory->createResponse(),
    );

    $framework->addDefinition(
        ResponseFactory::class,
        DI\factory([AppFactory::class, 'determineResponseFactory']),
    );

    $framework->addDefinition(
        RouteCollector::class,
        DI\autowire(SlimRoutingRouteCollector::class)
    );

    $framework->addDefinition(
        ServerRequest::class,
        fn () => ServerRequestCreatorFactory::create()
            ->createServerRequestFromGlobals(),
    );

    $framework->addDefinition(
        Session::class,
        DI\factory([DefaultSessionData::class, 'newEmptySession']),
    );

    $framework->addDefinition(
        SessionMiddleware::class,
        function (Settings $settings): SessionMiddleware {
            assert($settings['session'] instanceof SessionConfig);

            $settings['session']->testKey();

            return SessionMiddleware::fromSymmetricKeyDefaults(
                InMemory::plainText($settings['session']->key),
                $settings['session']->expires
            );
        },
    );

    $framework->addDefinition(
        Whoops::class,
        DI\autowire()->method('catchErrors', false)
    );
};
