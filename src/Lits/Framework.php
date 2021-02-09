<?php

declare(strict_types=1);

namespace Lits;

use DI\Container;
use DI\ContainerBuilder;
use DI\Definition\Helper\DefinitionHelper;
use GetOpt\GetOpt;
use Lits\Config\FrameworkConfig;
use Lits\Package\FrameworkPackage;
use Slim\App;
use Slim\Http\ServerRequest;

final class Framework
{
    private string $sapi;

    private App $app;
    private Container $container;
    private Settings $settings;

    /** @var array<class-string, callable|DefinitionHelper> */
    private array $definitions = [];

    /** @param list<Package> $packages */
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
        /** @var Settings $settings */
        $settings = $this->container->get(Settings::class);
        $this->settings = $settings;

        foreach ($packages as $package) {
            $package->settings($this);
        }

        // Check for commands from CLI environments.
        $this->checkForCommands();

        // Check for HTTPS proxies based upon settings.
        $this->checkForProxies();

        // Create an application from the container.
        /** @var App $app */
        $app = $this->container->get(App::class);
        $this->app = $app;

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

    public function run(): void
    {
        /** @var ServerRequest */
        $request = $this->container->get(ServerRequest::class);

        $this->app->run($request);
    }

    /**
     * @param class-string $class
     * @param callable|DefinitionHelper $definition
     */
    public function addDefinition(string $class, $definition): void
    {
        $this->definitions[$class] = $definition;
    }

    public function addConfig(string $name, Config $config): void
    {
        $this->settings[$name] = $config;
    }

    private function checkForCommands(): void
    {
        // Only check for commands when using PHP from the command line.
        if ($this->sapi !== 'cli') {
            return;
        }

        // Set the REQUEST_METHOD to GET by default.
        if (!isset($_SERVER['REQUEST_METHOD'])) {
            $_SERVER['REQUEST_METHOD'] = 'GET';
        }

        /** @var GetOpt */
        $getopt = $this->container->get(GetOpt::class);
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
        // REMOTE_ADDR header must be specified, and
        // HTTP_X_FORWARDED_PROTO header must be https.
        if (
            !isset($_SERVER['REMOTE_ADDR']) ||
            !isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ||
            $_SERVER['HTTP_X_FORWARDED_PROTO'] !== 'https'
        ) {
            return;
        }

        // REMOTE_ADDR header must be in the proxies config.
        \assert($this->settings['framework'] instanceof FrameworkConfig);
        $proxies = (array) $this->settings['framework']->proxies;

        if (!\in_array($_SERVER['REMOTE_ADDR'], $proxies, true)) {
            return;
        }

        // Set REQUEST_SCHEME and SERVER_PORT headers correctly.
        $_SERVER['REQUEST_SCHEME'] = 'https';
        $_SERVER['SERVER_PORT'] = '443';
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
