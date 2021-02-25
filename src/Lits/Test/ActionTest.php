<?php

declare(strict_types=1);

namespace Lits\Test;

use Lits\Config\FrameworkConfig;
use Lits\Framework;
use Lits\Package\TestPackage;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface as Container;

final class ActionTest extends TestCase
{
    private Framework $framework;

    public function setUp(): void
    {
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_SCHEME'] = 'http';
        $_SERVER['REQUEST_URI'] = '/test/route';
        $_SERVER['SCRIPT_NAME'] = '/test/index.php';
        $_SERVER['SERVER_PORT'] = '80';

        $this->framework = new Framework([new TestPackage()], 'test');
    }

    public function testCanCreateSettings(): void
    {
        $settings = $this->framework->settings();

        self::assertInstanceOf(FrameworkConfig::class, $settings['framework']);
    }

    public function testCanCreateDefinitions(): void
    {
        $container = $this->framework->app()->getContainer();
        self::assertInstanceOf(Container::class, $container);
        self::assertTrue($container->get(TestPackage::class));
    }

    public function testCanCreateMiddlewareAndRoutes(): void
    {
        \ob_start();
        $this->framework->app()->run();
        self::assertEquals('test', \ob_get_clean());
    }

    public function testCanCheckForProxies(): void
    {
        /** @psalm-suppress PossiblyUndefinedStringArrayOffset */
        self::assertEquals('https', $_SERVER['REQUEST_SCHEME']);

        /** @psalm-suppress PossiblyUndefinedStringArrayOffset */
        self::assertEquals('443', $_SERVER['SERVER_PORT']);
    }

    public function testCanFindBasePath(): void
    {
        self::assertEquals('/test', $this->framework->app()->getBasePath());
    }
}
