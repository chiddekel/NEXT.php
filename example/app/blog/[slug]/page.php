<?php

declare(strict_types=1);

use Next\Component\Link;
use Next\Rendering\ViewContext;

return function (array $props, ViewContext $context): string {
    $slug = htmlspecialchars((string) ($props['params']['slug'] ?? 'post'), ENT_QUOTES, 'UTF-8');
    $title = ucwords(str_replace('-', ' ', $slug));

    $context->setTitle("{$title} - NEXT.php Blog");
    $context->setDescription("Detailed article about {$title}");

    $backLink = Link::to('/blog', '&larr; Back to all articles');

    return <<<HTML
<div style="margin-bottom: 24px;">
    {$backLink}
</div>
<article>
    <h1 style="font-size: 36px; margin-bottom: 8px;">{$title}</h1>
    <div style="color: #888; font-size: 14px; margin-bottom: 24px;">Published on October 22, 2026 &bull; Route: <code>/blog/[slug]</code></div>

    <p style="font-size: 18px; line-height: 1.8;">
        This article was rendered dynamically with parameter: <strong>{$slug}</strong>.
    </p>

    <p style="line-height: 1.8;">
        NEXT.php resolves dynamic route parameters directly from the directory name <code>[slug]</code>,
        passing them in <code>\$props['params']</code> and <code>ViewContext</code>.
    </p>
</article>
HTML;
};
