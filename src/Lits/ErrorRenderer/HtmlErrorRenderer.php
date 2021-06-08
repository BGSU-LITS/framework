<?php

declare(strict_types=1);

namespace Lits\ErrorRenderer;

use Lits\ErrorRenderer;
use Lits\Exception\InvalidTemplateException;
use Lits\Template;
use Throwable;

final class HtmlErrorRenderer extends ErrorRenderer
{
    protected Template $template;

    public function __construct(Template $template)
    {
        $this->template = $template;

        parent::__construct();
    }

    /** @throws InvalidTemplateException */
    public function __invoke(
        Throwable $exception,
        bool $displayErrorDetails
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
