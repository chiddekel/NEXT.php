<?php

declare(strict_types=1);

use Next\Rendering\ViewContext;

return function (array $props, ViewContext $context): string {
    $children = $props['children'] ?? '';

    return <<<HTML
<div style="display: flex; gap: 30px;">
    <aside style="width: 220px; flex-shrink: 0; border-right: 1px solid #eaeaea; padding-right: 20px;">
        <h4 style="margin-top:0;text-transform:uppercase;font-size:12px;letter-spacing:1px;color:#888;">Blog Categories</h4>
        <ul style="list-style:none;padding:0;margin:0;line-height:2;">
            <li><a href="/blog" style="color:#111;text-decoration:none;">All Articles</a></li>
            <li><a href="/blog/architecture" style="color:#666;text-decoration:none;">Architecture</a></li>
            <li><a href="/blog/solid-php" style="color:#666;text-decoration:none;">SOLID in PHP</a></li>
            <li><a href="/blog/performance" style="color:#666;text-decoration:none;">Performance</a></li>
        </ul>
    </aside>
    <div style="flex-grow: 1;">
        {$children}
    </div>
</div>
HTML;
};
