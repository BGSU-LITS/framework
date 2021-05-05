<?php

declare(strict_types=1);

namespace Lits\ErrorRenderer;

use Lits\ErrorRenderer;
use Throwable;

final class PlainTextErrorRenderer extends ErrorRenderer
{
    public function __invoke(
        Throwable $exception,
        bool $displayErrorDetails
    ): string {
        return $this->getErrorTitle($exception) . \PHP_EOL;
    }
}
