<?php

declare(strict_types=1);

use Next\Component\Link;
use Next\Rendering\ViewContext;

return function (array $props, ViewContext $context): string {
    $context->setTitle('Blog - NEXT.php');

    $post1 = Link::to('/blog/architecture', 'Read Architecture Deep-Dive &rarr;');
    $post2 = Link::to('/blog/solid-php', 'Read SOLID Principles in Action &rarr;');

    return <<<HTML
<h1>Engineering Blog</h1>
<p style="color:#666;">Latest news, guides, and updates from the NEXT.php project.</p>

<div class="card" style="margin-top:24px;">
    <h2>Next.js Architecture Reimagined for PHP</h2>
    <p>Discover how the App Router, file-system routing, and server actions translate cleanly to modern PHP 8.5+.</p>
    {$post1}
</div>

<div class="card">
    <h2>Strict SOLID Principles with Zero Framework Bloat</h2>
    <p>How we achieved zero external runtime dependencies while preserving complete Next.js fidelity.</p>
    {$post2}
</div>
HTML;
};
