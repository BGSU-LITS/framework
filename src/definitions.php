<?php

declare(strict_types=1);

use Dflydev\FigCookies\Modifier\SameSite;
use Dflydev\FigCookies\SetCookie;
use GetOpt\GetOpt;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration as SessionConfiguration;
use Lcobucci\JWT\Signer\Hmac\Sha256 as SignerHmacSha256;
use Lcobucci\JWT\Signer\Key\InMemory as SignerKeyInMemory;
use Lits\Config\FrameworkConfig;
use Lits\Config\SessionConfig;
use Lits\Config\TemplateConfig;
use Lits\ErrorHandler\FrameworkErrorHandler;
use Lits\ErrorRenderer\HtmlErrorRenderer;
use Lits\ErrorRenderer\PlainTextErrorRenderer;
use Lits\Framework;
use Lits\Settings;
use Middlewares\Whoops;
use Monolog\Formatter\LineFormatter;
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
use Symfony\Component\EventDispatcher\EventDispatcher as SymfonyDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as Dispatcher;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

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
        Dispatcher::class,
        DI\create(SymfonyDispatcher::class)
    );

    $framework->addDefinition(
        Environment::class,
        function (Settings $settings): Environment {
            assert($settings['framework'] instanceof FrameworkConfig);
            assert($settings['template'] instanceof TemplateConfig);

            $paths = [];

            if (!is_null($settings['template']->paths)) {
                $paths = array_reverse($settings['template']->paths);
            }

            return new Environment(
                new FilesystemLoader($paths),
                [
                    'cache' => $settings['template']->cache ?? false,
                    'debug' => $settings['framework']->debug ?? false,
                ]
            );
        }
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
        FrameworkErrorHandler::class,
        DI\autowire()
            ->method(
                'registerErrorRenderer',
                'text/html',
                HtmlErrorRenderer::class
            )
            ->method(
                'registerErrorRenderer',
                'text/plain',
                PlainTextErrorRenderer::class
            )
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

            // If log path is specified, create a stream handler and formatter.
            assert($settings['framework'] instanceof FrameworkConfig);

            if (!is_null($settings['framework']->log)) {
                $handler = new StreamHandler($settings['framework']->log);

                // Set the formatter in a similar format for error_log.
                $handler->setFormatter(new LineFormatter(
                    "[%datetime%] %channel%.%level_name%: %message%\n",
                    'd-M-Y H:i:s e',
                    true,
                ));

                $logger->pushHandler($handler);
            }

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
        fn () => AppFactory::determineResponseFactory(),
    );

    $framework->addDefinition(
        RouteCollector::class,
        DI\get(SlimRoutingRouteCollector::class)
    );

    $framework->addDefinition(
        ServerRequest::class,
        fn () => ServerRequestCreatorFactory::create()
            ->createServerRequestFromGlobals(),
    );

    $framework->addDefinition(
        Session::class,
        fn () => DefaultSessionData::newEmptySession()
    );

    $framework->addDefinition(
        SessionConfiguration::class,
        function (Settings $settings): SessionConfiguration {
            assert($settings['session'] instanceof SessionConfig);

            $settings['session']->testKey();

            return SessionConfiguration::forSymmetricSigner(
                new SignerHmacSha256(),
                SignerKeyInMemory::plainText($settings['session']->key)
            );
        }
    );

    $framework->addDefinition(
        SessionMiddleware::class,
        function (
            Settings $settings,
            SessionConfiguration $configuration
        ): SessionMiddleware {
            assert($settings['session'] instanceof SessionConfig);

            return new SessionMiddleware(
                $configuration,
                SetCookie::create('__Host-lits-session')
                    ->withSecure(true)
                    ->withHttpOnly(true)
                    ->withSameSite(SameSite::lax())
                    ->withPath('/'),
                $settings['session']->expires,
                SystemClock::fromSystemTimezone()
            );
        },
    );

    $framework->addDefinition(
        Whoops::class,
        DI\autowire()->method('catchErrors', false)
    );
};
