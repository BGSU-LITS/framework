<?php

declare(strict_types=1);

namespace Lits\Test;

use Lits\Framework;
use Lits\Package\TestPackage;
use PHPUnit\Framework\TestCase;

use function Safe\ob_get_clean;
use function Safe\ob_start;

final class CommandTest extends TestCase
{
    private Framework $framework;

    #[\Override]
    public function setUp(): void
    {
        $unset = [
            'HTTP_X_FORWARDED_PROTO',
            'REMOTE_ADDR',
            'REQUEST_METHOD',
            'REQUEST_SCHEME',
            'REQUEST_URI',
            'SCRIPT_NAME',
            'SERVER_PORT',
        ];

        foreach ($unset as $key) {
            unset($_SERVER[$key]);
        }

        $_SERVER['argv'] = ['/test/route.php'];

        $this->framework = new Framework([new TestPackage()], 'cli');
    }

    /** @throws \Safe\Exceptions\OutcontrolException */
    public function testCanCreateMiddlewareAndRoutes(): void
    {
        ob_start();
        $this->framework->app()->run();
        self::assertEquals('test', ob_get_clean());
    }

    public function testCanCheckForCommands(): void
    {
        /** @psalm-suppress PossiblyUndefinedArrayOffset */
        self::assertEquals('GET', $_SERVER['REQUEST_METHOD']);

        /** @psalm-suppress PossiblyUndefinedArrayOffset */
        self::assertEquals('/route', $_SERVER['REQUEST_URI']);
    }
}
