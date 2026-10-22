<?php

declare(strict_types=1);

namespace Next\Component;

/**
 * Next.js Link component helper.
 */
class Link
{
    /**
     * Render an anchor tag optimized for Next.js SPA transitions.
     *
     * @param array<string, string|int|bool> $attributes
     */
    public static function to(string $href, string $content, array $attributes = []): string
    {
        $attrs = array_merge([
            'href' => $href,
            'data-next-link' => 'true',
        ], $attributes);

        $htmlAttrs = [];
        foreach ($attrs as $name => $value) {
            if ($value === true) {
                $htmlAttrs[] = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            } elseif ($value !== false && $value !== null) {
                $htmlAttrs[] = htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"';
            }
        }

        return '<a ' . implode(' ', $htmlAttrs) . '>' . $content . '</a>';
    }
}
