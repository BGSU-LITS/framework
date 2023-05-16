<?php

declare(strict_types=1);

namespace Lits\Config;

use Lits\Config;

final class TemplateConfig extends Config
{
    public ?string $cache = null;

    /** @var ?list<array<mixed>> */
    public ?array $menu = null;

    /** @var ?list<string> */
    public ?array $paths = null;

    public ?string $site = null;

    public ?string $analytics = null;

    public ?string $contact = null;
}
