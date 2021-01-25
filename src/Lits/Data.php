<?php

declare(strict_types=1);

namespace Lits;

abstract class Data
{
    protected Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    protected static function separator(
        string $path,
        string $separator = \DIRECTORY_SEPARATOR
    ): string {
        return \rtrim($path, $separator) . $separator;
    }
}
