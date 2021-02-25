<?php

declare(strict_types=1);

namespace Lits\Config;

use Lits\Config;

final class FrameworkConfig extends Config
{
    public ?bool $debug = null;
    public ?string $log = null;
}
