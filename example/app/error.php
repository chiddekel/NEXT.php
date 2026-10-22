<?php

declare(strict_types=1);

use Next\Rendering\ViewContext;

return function (array $props, ViewContext $context): string {
    $error = $props['error'] ?? null;
    $message = $error instanceof Throwable ? $error->getMessage() : 'An unexpected error occurred';

    $context->setTitle('500 - Application Error');

    return <<<HTML
<div style="border: 1px solid #ff4d4f; background: #fff2f0; padding: 24px; border-radius: 8px;">
    <h2 style="color: #cf1322; margin-top: 0;">Application Error Encountered</h2>
    <p style="color: #434343;">{$message}</p>
    <button onclick="window.location.reload()" style="background:#ff4d4f;color:#fff;border:none;padding:8px 16px;border-radius:4px;cursor:pointer;">Try Again</button>
</div>
HTML;
};
