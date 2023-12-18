<?php

declare(strict_types=1);

namespace Lits;

abstract class Data
{
    public function __construct(protected Settings $settings)
    {
    }

    protected static function separator(
        string $path,
        string $separator = \DIRECTORY_SEPARATOR,
    ): string {
        return \rtrim($path, $separator) . $separator;
    }
}
