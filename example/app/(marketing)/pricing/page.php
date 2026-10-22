<?php

declare(strict_types=1);

use Next\Rendering\ViewContext;

return function (array $props, ViewContext $context): string {
    $context->setTitle('Pricing Plans - NEXT.php');

    return <<<HTML
<div style="text-align: center; margin-bottom: 40px;">
    <h1>Simple, Predictable Pricing</h1>
    <p style="color:#666;">Route Group demonstration: <code>app/(marketing)/pricing/page.php</code> mapped to <code>/pricing</code></p>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px;">
    <div class="card" style="text-align:center;">
        <h3>Open Source</h3>
        <div style="font-size: 32px; font-weight: 800; margin: 16px 0;">$0</div>
        <p style="color:#666;">Free forever under MIT license.</p>
        <button style="background:#000;color:#fff;border:none;padding:10px 20px;border-radius:6px;cursor:pointer;">Get Started</button>
    </div>
    <div class="card" style="text-align:center;border-color:#0070f3;">
        <h3>Enterprise</h3>
        <div style="font-size: 32px; font-weight: 800; margin: 16px 0; color:#0070f3;">Custom</div>
        <p style="color:#666;">Dedicated support & custom deployment.</p>
        <button style="background:#0070f3;color:#fff;border:none;padding:10px 20px;border-radius:6px;cursor:pointer;">Contact Us</button>
    </div>
</div>
HTML;
};
