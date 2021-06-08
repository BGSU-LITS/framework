<?php

declare(strict_types=1);

namespace Lits;

use Lits\Config\FrameworkConfig;
use Lits\Config\TemplateConfig;
use Lits\Exception\FailedRoutingException;
use Lits\Exception\InvalidTemplateException;
use Psr\Http\Message\ServerRequestInterface as ServerRequest;
use Slim\Interfaces\RouteCollectorInterface as RouteCollector;
use Slim\Interfaces\RouteParserInterface as RouteParser;
use Throwable;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class Template
{
    private Environment $environment;
    private ServerRequest $request;
    private RouteParser $routeParser;

    public function __construct(
        ServerRequest $request,
        RouteCollector $routeCollector,
        Settings $settings
    ) {
        $this->routeParser = $routeCollector->getRouteParser();
        $this->request = $request;

        \assert($settings['framework'] instanceof FrameworkConfig);
        \assert($settings['template'] instanceof TemplateConfig);

        $paths = [];

        if (!\is_null($settings['template']->paths)) {
            $paths = \array_reverse($settings['template']->paths);
        }

        $this->environment = new Environment(
            new FilesystemLoader($paths),
            [
                'cache' => $settings['template']->cache ?? false,
                'debug' => $settings['framework']->debug ?? false,
            ]
        );

        $this->environment->addGlobal('settings', $settings);

        $this->environment->addFunction(new TwigFunction(
            'url_for',
            [$this->routeParser, 'urlFor']
        ));

        $this->environment->addFunction(new TwigFunction(
            'path_for',
            [$this->routeParser, 'urlFor']
        ));

        $this->environment->addFunction(new TwigFunction(
            'full_url_for',
            [$this, 'fullUrlFor']
        ));

        $this->environment->addFunction(new TwigFunction(
            'full_path_for',
            [$this, 'fullUrlFor']
        ));

        $this->environment->addFunction(new TwigFunction(
            'relative_url_for',
            [$this->routeParser, 'relativeUrlFor']
        ));

        $this->environment->addFunction(new TwigFunction(
            'relative_path_for',
            [$this->routeParser, 'relativeUrlFor']
        ));

        $this->environment->addFunction(new TwigFunction(
            'current_url',
            [$this, 'currentUrl']
        ));

        $this->environment->addFunction(new TwigFunction(
            'current_path',
            [$this, 'currentUrl']
        ));

        $this->environment->addFunction(new TwigFunction(
            'is_current_url',
            [$this, 'isCurrentUrl']
        ));

        $this->environment->addFunction(new TwigFunction(
            'is_current_path',
            [$this, 'isCurrentUrl']
        ));
    }

    /**
     * @param array<string, mixed> $context
     * @throws InvalidTemplateException
     */
    public function render(string $name, array $context = []): string
    {
        try {
            return $this->environment->render($name, $context);
        } catch (Throwable $exception) {
            throw new InvalidTemplateException(
                'The requested template could not be rendered',
                0,
                $exception
            );
        }
    }

    /** @throws FailedRoutingException */
    public function currentUrl(bool $withQueryString = false): string
    {
        try {
            $url = $this->request->getUri()->getPath();

            if ($withQueryString) {
                $query = $this->request->getUri()->getQuery();

                if ($query !== '') {
                    return $url . '?' . $query;
                }
            }

            return $url;
        } catch (Throwable $exception) {
            throw new FailedRoutingException(
                'The URL could not be obtained',
                0,
                $exception
            );
        }
    }

    /**
     * @param array<string> $data
     * @param array<string> $queryParams
     * @throws FailedRoutingException
     */
    public function fullUrlFor(
        string $routeName,
        array $data = [],
        array $queryParams = []
    ): string {
        try {
            return $this->routeParser->fullUrlFor(
                $this->request->getUri(),
                $routeName,
                $data,
                $queryParams
            );
        } catch (Throwable $exception) {
            throw new FailedRoutingException(
                'The URL could not be obtained',
                0,
                $exception
            );
        }
    }

    /**
     * @param array<string> $data
     * @throws FailedRoutingException
     */
    public function isCurrentUrl(string $routeName, array $data = []): bool
    {
        try {
            $url = $this->routeParser->urlFor($routeName, $data);

            return $url === $this->currentUrl();
        } catch (Throwable $exception) {
            throw new FailedRoutingException(
                'The URL could not be obtained',
                0,
                $exception
            );
        }
    }
}
