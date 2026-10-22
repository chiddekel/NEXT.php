<?php

declare(strict_types=1);

use Next\Component\Link;
use Next\Rendering\ViewContext;

return function (array $props, ViewContext $context): string {
    $context->setTitle('Welcome to NEXT.php');

    $blogLink = Link::to('/blog', 'Explore our Blog &rarr;');
    $pricingLink = Link::to('/pricing', 'View Pricing Plans &rarr;');
    $apiLink = Link::to('/api/hello', 'Test /api/hello endpoint &rarr;');

    return <<<HTML
<div style="text-align: center; padding: 40px 0;">
    <h1 style="font-size: 48px; font-weight: 800; letter-spacing: -1.5px; margin-bottom: 16px;">
        The Next.js Framework for PHP
    </h1>
    <p style="font-size: 20px; color: #666; max-width: 600px; margin: 0 auto 40px;">
        File-system routing, nested layouts, API routes, middleware, and ISR caching built on pure modern PHP.
    </p>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px;">
    <div class="card">
        <h3>App Router & Nested Layouts</h3>
        <p>Seamlessly compose your UI with root and nested <code>layout.php</code> hierarchies.</p>
        {$blogLink}
    </div>
    <div class="card">
        <h3>Route Groups & Dynamic Segments</h3>
        <p>Support for <code>(group)</code> folders, <code>[slug]</code> parameters, and catch-all routes.</p>
        {$pricingLink}
    </div>
    <div class="card">
        <h3>Route Handlers (API)</h3>
        <p>Modern server endpoints exporting <code>GET</code>, <code>POST</code>, and other HTTP methods.</p>
        {$apiLink}
    </div>
</div>
HTML;
};
