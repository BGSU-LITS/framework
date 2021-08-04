<?php

declare(strict_types=1);

namespace Lits;

use DI\Container;
use DI\ContainerBuilder;
use DI\Definition\Helper\DefinitionHelper;
use DI\Definition\Reference;
use GetOpt\GetOpt;
use Lits\Config\FrameworkConfig;
use Lits\Exception\InvalidConfigException;
use Lits\Exception\InvalidDependencyException;
use Lits\Package\FrameworkPackage;
use Slim\App;
use Slim\Http\ServerRequest;

final class Framework
{
    private string $sapi;

    private App $app;
    private Container $container;
    private Settings $settings;

    /** @var array<class-string, callable|DefinitionHelper|Reference> */
    private array $definitions = [];

    /**
     * @param list<Package> $packages
     * @throws InvalidConfigException
     * @throws InvalidDependencyException
     */
    public function __construct(array $packages = [], string $sapi = \PHP_SAPI)
    {
        $this->sapi = $sapi;

        // Add the framework package by default.
        \array_unshift($packages, new FrameworkPackage());

        // Create the container by loading definitions from all packages.
        foreach ($packages as $package) {
            $package->definitions($this);
        }

        $builder = new ContainerBuilder();
        $builder->addDefinitions($this->definitions);
        $this->container = $builder->build();

        // Create the settings by loading configs from all packages.
        try {
            /** @var Settings $settings */
            $settings = $this->container->get(Settings::class);
            $this->settings = $settings;
        } catch (\Throwable $exception) {
            throw new InvalidDependencyException(
                'Could not load dependency from container',
                0,
                $exception
            );
        }

        foreach ($packages as $package) {
            $package->settings($this);
        }

        // Check for commands from CLI environments.
        $this->checkForCommands();

        // Check for HTTPS proxies based upon settings.
        $this->checkForProxies();

        // Check for a default timezone.
        $this->checkForTimezone();

        // Create an application from the container.
        try {
            /** @var App $app */
            $app = $this->container->get(App::class);
            $this->app = $app;
        } catch (\Throwable $exception) {
            throw new InvalidDependencyException(
                'Could not load dependency from container',
                0,
                $exception
            );
        }

        // Find the base path in case project is run from a subdirectory.
        $this->findBasePath();

        // Add any middleware from packages to the application.
        foreach ($packages as $package) {
            $package->middleware($this);
        }

        // Add any routes from packages to the application.
        foreach ($packages as $package) {
            $package->routes($this);
        }
    }

    public function app(): App
    {
        return $this->app;
    }

    public function settings(): Settings
    {
        return $this->settings;
    }

    /** @throws InvalidDependencyException */
    public function run(): void
    {
        try {
            /** @var ServerRequest */
            $request = $this->container->get(ServerRequest::class);
        } catch (\Throwable $exception) {
            throw new InvalidDependencyException(
                'Could not load dependency from container',
                0,
                $exception
            );
        }

        $this->app->run($request);
    }

    /**
     * @param class-string $class
     * @param callable|DefinitionHelper|Reference $definition
     */
    public function addDefinition(string $class, $definition): void
    {
        $this->definitions[$class] = $definition;
    }

    public function addConfig(string $name, Config $config): void
    {
        $this->settings[$name] = $config;
    }

    /** @throws InvalidDependencyException */
    private function checkForCommands(): void
    {
        // Only check for commands when using PHP from the command line.
        if ($this->sapi !== 'cli') {
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

        try {
            /** @var GetOpt */
            $getopt = $this->container->get(GetOpt::class);
        } catch (\Throwable $exception) {
            throw new InvalidDependencyException(
                'Could not load dependency from container',
                0,
                $exception
            );
        }

        $getopt->process();

        // Unless a REQUEST_URI is specified, build it from command.
        if (!isset($_SERVER['REQUEST_URI'])) {
            $_SERVER['REQUEST_URI'] = '/' . \basename(
                (string) $getopt->get(GetOpt::SETTING_SCRIPT_NAME),
                '.php'
            );
        }

        // Unless a QUERY_STRING is specified, build it from command args.
        if (isset($_SERVER['QUERY_STRING'])) {
            return;
        }

        $_SERVER['QUERY_STRING'] = \http_build_query(
            $getopt->getOptions() + $getopt->getOperands()
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

        if (\is_null($this->settings['framework']->timezone)) {
            return;
        }

        $timezone = $this->settings['framework']->timezone;

        if (!\date_default_timezone_set($timezone)) {
            throw new InvalidConfigException(
                'The timezone "' . $timezone . '" is not valid'
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
        $basePath = \dirname((string) $_SERVER['SCRIPT_NAME']);

        // Set the base path if a directory was found.
        if ($basePath !== '/') {
            $this->app->setBasePath($basePath);
        }
    }
}
