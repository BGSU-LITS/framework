<?php

declare(strict_types=1);

use Lits\Config\FrameworkConfig;
use Lits\Config\SessionConfig;
use Lits\Config\TemplateConfig;
use Lits\Framework;

return function (Framework $framework): void {
    $framework->addConfig('framework', new FrameworkConfig());
    $framework->addConfig('session', new SessionConfig());

    $template = new TemplateConfig();
    $template->paths = array_merge(
        (array) $template->paths,
        [dirname(__DIR__) . DIRECTORY_SEPARATOR . 'template'],
    );

    $framework->addConfig('template', $template);
};
