<?php

declare(strict_types=1);

use Next\Rendering\ViewContext;

return function (array $props, ViewContext $context): string {
    $slugParts = (array) ($props['params']['slug'] ?? []);
    $fullPath = implode(' / ', array_map('htmlspecialchars', $slugParts));

    $context->setTitle("Documentation: {$fullPath} - NEXT.php");

    return <<<HTML
<h1>Documentation</h1>
<p style="color:#666;">Catch-all route demonstration: <code>/docs/[...slug]</code></p>

<div class="card" style="margin-top:20px;">
    <h3>Active Breadcrumbs</h3>
    <p><code>{$fullPath}</code></p>
    <p>Catch-all parameters received:</p>
    <pre style="background:#fafafa;padding:12px;border:1px solid #eaeaea;border-radius:4px;">{$context->renderHead()}</pre>
</div>
HTML;
};
