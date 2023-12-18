<?php

declare(strict_types=1);

namespace Lits\ErrorRenderer;

use Lits\ErrorRenderer;
use Lits\Exception\InvalidTemplateException;
use Lits\Template;

final class HtmlErrorRenderer extends ErrorRenderer
{
    public function __construct(protected Template $template)
    {
        parent::__construct();
    }

    /** @throws InvalidTemplateException */
    public function __invoke(
        \Throwable $exception,
        bool $displayErrorDetails,
    ): string {
        return $this->template->render('error.html.twig', [
            'title' => $this->getErrorTitle($exception),
            'messages' => [[
                'level' => 'failure',
                'message' => $this->getErrorDescription($exception),
            ]],
        ]);
    }
}
