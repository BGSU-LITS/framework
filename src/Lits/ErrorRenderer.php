<?php

declare(strict_types=1);

namespace Lits;

use Slim\Error\AbstractErrorRenderer;

abstract class ErrorRenderer extends AbstractErrorRenderer
{
    public function __construct()
    {
        $this->defaultErrorTitle =
            'Unexpected Error';
        $this->defaultErrorDescription =
            'An unexpected error occurred, please try again.';
    }
}
