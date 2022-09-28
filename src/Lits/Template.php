<?php

declare(strict_types=1);

namespace Lits;

use Lits\Exception\FailedRoutingException;
use Lits\Exception\InvalidTemplateException;
use Psr\Http\Message\ServerRequestInterface as ServerRequest;
use Slim\Interfaces\RouteCollectorInterface as RouteCollector;
use Slim\Interfaces\RouteParserInterface as RouteParser;
use Twig\Environment;
use Twig\TwigFunction;

final class Template
{
    private Environment $environment;
    private ServerRequest $request;
    private RouteParser $routeParser;

    public function __construct(
        Environment $environment,
        ServerRequest $request,
        RouteCollector $routeCollector,
        Settings $settings
    ) {
        $this->environment = $environment;
        $this->routeParser = $routeCollector->getRouteParser();
        $this->request = $request;

        $this->global('settings', $settings);

        $this->function('url_for', [$this->routeParser, 'urlFor']);
        $this->function('path_for', [$this->routeParser, 'urlFor']);
        $this->function('full_url_for', [$this, 'fullUrlFor']);
        $this->function('full_path_for', [$this, 'fullUrlFor']);

        $this->function(
            'relative_url_for',
            [$this->routeParser, 'relativeUrlFor']
        );

        $this->function(
            'relative_path_for',
            [$this->routeParser, 'relativeUrlFor']
        );

        $this->function('current_url', [$this, 'currentUrl']);
        $this->function('current_path', [$this, 'currentUrl']);
        $this->function('is_current_url', [$this, 'isCurrentUrl']);
        $this->function('is_current_path', [$this, 'isCurrentUrl']);
    }

    public function function(string $name, callable $callable): void
    {
        $this->environment->addFunction(new TwigFunction($name, $callable));
    }

    /** @param mixed $value */
    public function global(string $name, $value): void
    {
        $this->environment->addGlobal($name, $value);
    }

    /**
     * @param array<string, mixed> $context
     * @throws InvalidTemplateException
     */
    public function render(string $name, array $context = []): string
    {
        try {
            return $this->environment->render($name, $context);
        } catch (\Throwable $exception) {
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
        } catch (\Throwable $exception) {
            throw new FailedRoutingException(
                'The URL could not be obtained',
                0,
                $exception
            );
        }
    }

    /**
     * @param array<string, string> $data
     * @param array<string, string> $queryParams
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
        } catch (\Throwable $exception) {
            throw new FailedRoutingException(
                'The URL could not be obtained',
                0,
                $exception
            );
        }
    }

    /**
     * @param array<string, string> $data
     * @throws FailedRoutingException
     */
    public function isCurrentUrl(string $routeName, array $data = []): bool
    {
        try {
            $url = $this->routeParser->urlFor($routeName, $data);

            return $url === $this->currentUrl();
        } catch (\Throwable $exception) {
            throw new FailedRoutingException(
                'The URL could not be obtained',
                0,
                $exception
            );
        }
    }
}
