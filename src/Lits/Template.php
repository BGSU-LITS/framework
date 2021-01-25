<?php

declare(strict_types=1);

namespace Lits;

use Lits\Config\FrameworkConfig;
use Lits\Config\TemplateConfig;
use Psr\Http\Message\ServerRequestInterface as ServerRequest;
use Slim\Interfaces\RouteCollectorInterface as RouteCollector;
use Slim\Interfaces\RouteParserInterface as RouteParser;
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
            'relative_url_for',
            [$this->routeParser, 'relativeUrlFor']
        ));

        $this->environment->addFunction(new TwigFunction(
            'url_for',
            [$this->routeParser, 'urlFor']
        ));

        $this->environment->addFunction(new TwigFunction(
            'full_url_for',
            [$this->routeParser, 'fullUrlFor']
        ));

        $this->environment->addFunction(new TwigFunction(
            'current_path',
            [$this, 'currentPath']
        ));

        $this->environment->addFunction(new TwigFunction(
            'is_current_path',
            [$this, 'isCurrentPath']
        ));
    }

    /** @param array<string, mixed> $context */
    public function render(string $name, array $context = []): string
    {
        return $this->environment->render($name, $context);
    }

    public function currentPath(bool $withQueryString = false): string
    {
        $path = $this->request->getUri()->getPath();

        if ($withQueryString) {
            $query = $this->request->getUri()->getQuery();

            if ($query !== '') {
                return $path . '?' . $query;
            }
        }

        return $path;
    }

    /** @param array<string> $data */
    public function isCurrentPath(string $routeName, array $data = []): bool
    {
        $path = $this->routeParser->urlFor($routeName, $data);

        return $path === $this->currentPath();
    }
}
