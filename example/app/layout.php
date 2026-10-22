<?php

declare(strict_types=1);

use Next\Component\Link;
use Next\Rendering\ViewContext;

return function (array $props, ViewContext $context): string {
    $context->setTitle('NEXT.php - Next.js in PHP');
    $context->setDescription('The React-free Next.js App Router framework for PHP 8.5+');

    $children = $props['children'] ?? '';
    $path = $props['path'] ?? '/';

    $isHome = ($path === '/');
    $isBlog = str_starts_with($path, '/blog');
    $isPricing = str_starts_with($path, '/pricing');
    $isDocs = str_starts_with($path, '/docs');

    $navHome = Link::to('/', 'Home', ['class' => $isHome ? 'nav-link is-active' : 'nav-link', 'aria-current' => $isHome ? 'page' : null]);
    $navBlog = Link::to('/blog', 'Blog', ['class' => $isBlog ? 'nav-link is-active' : 'nav-link', 'aria-current' => $isBlog ? 'page' : null]);
    $navPricing = Link::to('/pricing', 'Pricing', ['class' => $isPricing ? 'nav-link is-active' : 'nav-link', 'aria-current' => $isPricing ? 'page' : null]);
    $navDocs = Link::to('/docs/getting-started', 'Docs', ['class' => $isDocs ? 'nav-link is-active' : 'nav-link', 'aria-current' => $isDocs ? 'page' : null]);

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Meta injected automatically -->
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; margin: 0; padding: 0; color: #111; background: #fff; line-height: 1.6; }
        header { border-bottom: 1px solid #eaeaea; padding: 16px 32px; display: flex; align-items: center; justify-content: space-between; }
        .logo { font-weight: 800; font-size: 20px; letter-spacing: -0.5px; }
        .logo span { background: #000; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 14px; margin-left: 6px; }
        nav { display: flex; gap: 6px; align-items: center; }
        .nav-link {
            color: #555;
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            padding: 8px 14px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        .nav-link:hover {
            color: #000;
            background: #f4f4f4;
        }
        .nav-link.is-active, .nav-link[aria-current="page"] {
            color: #0070f3 !important;
            font-weight: 600 !important;
            background: #e6f0ff !important;
        }
        main { max-width: 900px; margin: 0 auto; padding: 40px 20px; min-height: 80vh; }
        footer { border-top: 1px solid #eaeaea; padding: 24px; text-align: center; color: #888; font-size: 14px; }
        .card { border: 1px solid #eaeaea; border-radius: 8px; padding: 20px; margin-bottom: 20px; transition: border-color 0.2s; }
        .card:hover { border-color: #0070f3; }
        code { background: #fafafa; border: 1px solid #eaeaea; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 14px; }
    </style>
</head>
<body>
    <header>
        <div class="logo">NEXT<span>php</span></div>
        <nav id="nav">
            {$navHome}
            {$navBlog}
            {$navPricing}
            {$navDocs}
        </nav>
    </header>
    <main id="swup" class="transition-fade">
        {$children}
    </main>
    <footer>
        Powered by <strong>NEXT.php</strong> &bull; SOLID &bull; YAGNI &bull; Swup.js Enabled
    </footer>
</body>
</html>
HTML;
};
