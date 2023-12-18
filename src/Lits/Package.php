<?php

declare(strict_types=1);

namespace Lits;

/**
 * @method null definitions(Framework $framework)
 * @method null events(Framework $framework)
 * @method null middleware(Framework $framework)
 * @method null routes(Framework $framework)
 * @method null settings(Framework $framework)
 */
abstract class Package
{
    public function path(): string
    {
        return \dirname(__DIR__);
    }

    /** @param list<Framework> $args */
    public function __call(string $name, array $args): void
    {
        $file = $this->path() . \DIRECTORY_SEPARATOR . $name . '.php';

        if (!\file_exists($file)) {
            return;
        }

        $result = require $file;

        if (\is_null($result)) {
            return;
        }

        \assert(\is_callable($result));
        $result(...$args);
    }
}
