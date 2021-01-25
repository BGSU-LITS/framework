<?php

declare(strict_types=1);

namespace Lits;

/** @extends \ArrayObject<string, Config> */
final class Settings extends \ArrayObject
{
    public function __construct()
    {
        parent::__construct([]);
    }
}
