<?php

declare(strict_types=1);

use Next\Component\Link;
use Next\Rendering\ViewContext;

return function (array $props, ViewContext $context): string {
    $context->setTitle('404 - Page Not Found');

    $homeLink = Link::to('/', '&larr; Return to Home');

    return <<<HTML
<div style="text-align: center; padding: 60px 0;">
    <h1 style="font-size: 72px; font-weight: 800; margin: 0; color: #111;">404</h1>
    <h2 style="font-size: 24px; font-weight: 500; color: #666; margin: 12px 0 24px;">This page could not be found.</h2>
    <div>
        {$homeLink}
    </div>
</div>
HTML;
};
