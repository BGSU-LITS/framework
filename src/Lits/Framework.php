<?php

declare(strict_types=1);

namespace Lits;

use DI\Container;
use DI\Definition\Helper\DefinitionHelper;
use DI\Definition\Reference;
use GetOpt\GetOpt;
use Lits\Config\FrameworkConfig;
use Lits\Exception\InvalidConfigException;
use Lits\Exception\InvalidDependencyException;
use Lits\Package\FrameworkPackage;
use Slim\App;
use Slim\Http\ServerRequest;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as Dispatcher;

final class Framework
{
    /** @var App<Container> */
    private App $app;

    private Container $container;
    private Dispatcher $dispatcher;
    private Settings $settings;

    /** @var array<class-string, callable|DefinitionHelper|Reference> */
    private array $definitions = [];

    /**
     * @param list<Package> $packages
     * @throws InvalidConfigException
     * @throws InvalidDependencyException
     */
    public function __construct(
        array $packages = [],
        private string $sapi = \PHP_SAPI,
    ) {

        // Add the framework package by default.
        \array_unshift($packages, new FrameworkPackage());

        // Create the container by loading definitions from all packages.
        foreach ($packages as $package) {
            $package->definitions($this);
        }

        $this->container = new Container($this->definitions);

        $dispatcher = $this->getDependency(Dispatcher::class);

        \assert($dispatcher instanceof Dispatcher);
        $this->dispatcher = $dispatcher;

        foreach ($packages as $package) {
            $package->events($this);
        }

        $settings = $this->getDependency(Settings::class);

        \assert($settings instanceof Settings);
        $this->settings = $settings;

        foreach ($packages as $package) {
            $package->settings($this);
        }

        // Check for commands from CLI environments.
        $this->checkForCommands();

        // Check for HTTPS proxies based upon settings.
        $this->checkForProxies();

        // Check for a default timezone.
        $this->checkForTimezone();

        /** @var App<Container> $app */
        $app = $this->getDependency(App::class);
        $this->app = $app;

        // Find the base path in case project is run from a subdirectory.
        $this->findBasePath();

        // Add middleware from packages to the application in reverse order.
        foreach (\array_reverse($packages) as $package) {
            $package->middleware($this);
        }

        // Add any routes from packages to the application.
        foreach ($packages as $package) {
            $package->routes($this);
        }
    }

    /** @return App<Container> */
    public function app(): App
    {
        return $this->app;
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function dispatcher(): Dispatcher
    {
        return $this->dispatcher;
    }

    public function settings(): Settings
    {
        return $this->settings;
    }

    /** @throws InvalidDependencyException */
    public function run(): void
    {
        $request = $this->getDependency(ServerRequest::class);

        \assert($request instanceof ServerRequest);
        $this->app->run($request);
    }

    /** @param class-string $class */
    public function addDefinition(
        string $class,
        callable|DefinitionHelper|Reference $definition,
    ): void {
        $this->definitions[$class] = $definition;
    }

    public function addConfig(string $name, Config $config): void
    {
        $this->settings[$name] = $config;
    }

    public function isCli(): bool
    {
        return $this->sapi === 'cli';
    }

    /** @throws InvalidDependencyException */
    private function checkForCommands(): void
    {
        // Only check for commands when using PHP from the command line.
        if (!$this->isCli()) {
            return;
        }

        // Set HTTP_ACCEPT to text/plain by default.
        if (!isset($_SERVER['HTTP_ACCEPT'])) {
            $_SERVER['HTTP_ACCEPT'] = 'text/plain';
        }

        // Set the REQUEST_METHOD to GET by default.
        if (!isset($_SERVER['REQUEST_METHOD'])) {
            $_SERVER['REQUEST_METHOD'] = 'GET';
        }

        $getopt = $this->getDependency(GetOpt::class);

        \assert($getopt instanceof GetOpt);
        $getopt->process();

        // Unless a REQUEST_URI is specified, build it from command.
        if (!isset($_SERVER['REQUEST_URI'])) {
            $_SERVER['REQUEST_URI'] = '/' . \basename(
                (string) $getopt->get(GetOpt::SETTING_SCRIPT_NAME),
                '.php',
            );
        }

        // Unless a QUERY_STRING is specified, build it from command args.
        if (isset($_SERVER['QUERY_STRING'])) {
            return;
        }

        $_SERVER['QUERY_STRING'] = \http_build_query(
            $getopt->getOptions() + $getopt->getOperands(),
        );
    }

    private function checkForProxies(): void
    {
        if (
            !isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ||
            $_SERVER['HTTP_X_FORWARDED_PROTO'] !== 'https'
        ) {
            return;
        }

        $_SERVER['REQUEST_SCHEME'] = 'https';
        $_SERVER['SERVER_PORT'] = '443';
    }

    /** @throws InvalidConfigException */
    private function checkForTimezone(): void
    {
        \assert($this->settings['framework'] instanceof FrameworkConfig);

        $timezone = $this->settings['framework']->timezone;

        if (\is_null($timezone) || $timezone === '') {
            return;
        }

        if (!\date_default_timezone_set($timezone)) {
            throw new InvalidConfigException(
                'The timezone "' . $timezone . '" is not valid',
            );
        }
    }

    /** @throws InvalidDependencyException */
    private function getDependency(string $class): mixed
    {
        try {
            return $this->container->get($class);
        } catch (\Throwable $exception) {
            throw new InvalidDependencyException(
                'Could not load dependency from container',
                0,
                $exception,
            );
        }
    }

    private function findBasePath(): void
    {
        // Do not attempt to determine a Base Path when run from the CLI.
        // SCRIPT_NAME must be specified to determine the actual base path.
        if ($this->sapi === 'cli' || !isset($_SERVER['SCRIPT_NAME'])) {
            return;
        }

        // Look for the directory of the SCRIPT_NAME.
        $basePath = \dirname($_SERVER['SCRIPT_NAME']);

        // Set the base path if a directory was found.
        if ($basePath !== '/') {
            $this->app->setBasePath($basePath);
        }
    }
}
