<?php

declare(strict_types=1);

namespace Next\Rendering;

use Next\Exception\NotFoundException;
use Next\Http\RequestInterface;
use Next\Routing\RouteMatch;
use Throwable;

/**
 * Handles rendering of Next.js App Router pages and nested layouts.
 */
class PageRenderer implements RendererInterface
{
    /**
     * Render a matched page route wrapped in its layout hierarchy.
     */
    public function render(RouteMatch $match, RequestInterface $request): string
    {
        $context = new ViewContext(
            params: $match->getParams(),
            searchParams: $request->getQueryParams(),
            path: $request->getPath()
        );

        $props = [
            'params' => $match->getParams(),
            'searchParams' => $request->getQueryParams(),
            'path' => $request->getPath(),
        ];

        // Render page
        $pageHtml = $this->evaluateComponent($match->getFilePath(), $props, $context);

        // Wrap inside layout hierarchy (leaf to root)
        $rendered = $pageHtml;
        $layoutFiles = array_reverse($match->getLayoutFiles());

        foreach ($layoutFiles as $layoutFile) {
            $layoutProps = array_merge($props, ['children' => $rendered]);
            $rendered = $this->evaluateComponent($layoutFile, $layoutProps, $context);
        }

        // Auto-inject metadata into <head> if not already done
        $rendered = $this->injectHeadMetadata($rendered, $context);

        return $rendered;
    }

    /**
     * Render 404 Not Found page wrapped in layouts.
     */
    public function renderNotFound(RequestInterface $request, ?string $notFoundFile = null, array $layoutFiles = []): string
    {
        $context = new ViewContext(
            params: [],
            searchParams: $request->getQueryParams(),
            metadata: ['title' => '404: This page could not be found'],
            path: $request->getPath()
        );

        $props = [
            'params' => [],
            'searchParams' => $request->getQueryParams(),
            'path' => $request->getPath(),
        ];

        $content = '';
        if ($notFoundFile !== null && file_exists($notFoundFile)) {
            $content = $this->evaluateComponent($notFoundFile, $props, $context);
        } else {
            $content = '<div style="font-family:system-ui,-apple-system,sans-serif;height:100vh;text-align:center;display:flex;flex-direction:column;align-items:center;justify-content:center;">'
                . '<div style="display:inline-block;"><h1 style="display:inline-block;margin:0 20px 0 0;padding-right:23px;font-size:24px;font-weight:500;vertical-align:top;border-right:1px solid rgba(0,0,0,.3);line-height:49px;">404</h1>'
                . '<div style="display:inline-block;text-align:left;"><h2 style="font-size:14px;font-weight:400;line-height:49px;margin:0;">This page could not be found.</h2></div></div></div>';
        }

        $rendered = $content;
        $reversedLayouts = array_reverse($layoutFiles);
        foreach ($reversedLayouts as $layoutFile) {
            $layoutProps = array_merge($props, ['children' => $rendered]);
            $rendered = $this->evaluateComponent($layoutFile, $layoutProps, $context);
        }

        return $this->injectHeadMetadata($rendered, $context);
    }

    /**
     * Render Error boundary page wrapped in layouts.
     */
    public function renderError(Throwable $error, RequestInterface $request, ?string $errorFile = null, array $layoutFiles = [], bool $isProduction = false): string
    {
        $context = new ViewContext(
            params: [],
            searchParams: $request->getQueryParams(),
            metadata: ['title' => 'Application Error']
        );

        $props = [
            'error' => $error,
            'params' => [],
            'searchParams' => $request->getQueryParams(),
        ];

        $content = '';
        if ($errorFile !== null && file_exists($errorFile)) {
            $content = $this->evaluateComponent($errorFile, $props, $context);
        } elseif ($isProduction) {
            $content = '<div style="font-family:system-ui,-apple-system,sans-serif;height:100vh;text-align:center;display:flex;flex-direction:column;align-items:center;justify-content:center;">'
                . '<div style="display:inline-block;"><h1 style="display:inline-block;margin:0 20px 0 0;padding-right:23px;font-size:24px;font-weight:500;vertical-align:top;border-right:1px solid rgba(0,0,0,.3);line-height:49px;">500</h1>'
                . '<div style="display:inline-block;text-align:left;"><h2 style="font-size:14px;font-weight:400;line-height:49px;margin:0;">Internal Server Error.</h2></div></div></div>';
        } else {
            $escapedMsg = htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8');
            $content = '<div style="font-family:system-ui,-apple-system,sans-serif;padding:40px;max-width:800px;margin:0 auto;">'
                . '<h1 style="color:#e11d48;font-size:24px;">Unhandled Server Exception</h1>'
                . '<p style="color:#374151;font-size:16px;">' . $escapedMsg . '</p>'
                . '<pre style="background:#f3f4f6;padding:16px;border-radius:8px;overflow-x:auto;font-size:13px;">'
                . htmlspecialchars($error->getTraceAsString(), ENT_QUOTES, 'UTF-8')
                . '</pre></div>';
        }

        $rendered = $content;
        $reversedLayouts = array_reverse($layoutFiles);
        foreach ($reversedLayouts as $layoutFile) {
            $layoutProps = array_merge($props, ['children' => $rendered]);
            $rendered = $this->evaluateComponent($layoutFile, $layoutProps, $context);
        }

        return $this->injectHeadMetadata($rendered, $context);
    }

    /**
     * Safely executes a component or layout file.
     * Supports both:
     * 1. Files returning a callable: `return function(array $props, ViewContext $context) { ... };`
     * 2. Files with HTML/PHP templates echoing or outputting content directly.
     */
    private function evaluateComponent(string $filePath, array $props, ViewContext $context): string
    {
        if (!file_exists($filePath)) {
            return $props['children'] ?? '';
        }

        $children = $props['children'] ?? '';
        $params = $props['params'] ?? [];
        $searchParams = $props['searchParams'] ?? [];
        $error = $props['error'] ?? null;

        // Isolate evaluation scope
        $executor = function () use ($filePath, $props, $context, $children, $params, $searchParams, $error) {
            ob_start();
            $result = require $filePath;
            $output = ob_get_clean();

            if (is_callable($result)) {
                $closureResult = $result($props, $context);
                return is_string($closureResult) ? $closureResult : (string) $output;
            }

            if (is_string($result) && $result !== '1') {
                return $result;
            }

            return (string) $output;
        };

        return $executor();
    }

    /**
     * Auto-injects ViewContext metadata into <head> tag if present and not already injected.
     */
    private function injectHeadMetadata(string $html, ViewContext $context): string
    {
        $headTags = $context->renderHead();
        if ($headTags === '') {
            return $html;
        }

        // If <head> exists, insert metadata inside it
        if (stripos($html, '<head>') !== false) {
            return (string) preg_replace(
                '/<head>/i',
                "<head>\n    " . $headTags,
                $html,
                1
            );
        }

        // If no <head>, but <html> exists
        if (stripos($html, '<html') !== false) {
            return (string) preg_replace(
                '/(<html[^>]*>)/i',
                "$1\n<head>\n    " . $headTags . "\n</head>",
                $html,
                1
            );
        }

        return $html;
    }
}
