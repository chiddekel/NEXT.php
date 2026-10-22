<?php

declare(strict_types=1);

namespace Next\Component;

/**
 * Next.js Image component helper.
 */
class Image
{
    /**
     * Render an optimized img tag preventing layout shift.
     *
     * @param array<string, string|int|bool> $attributes
     */
    public static function render(
        string $src,
        string $alt,
        int $width,
        int $height,
        bool $priority = false,
        array $attributes = []
    ): string {
        $attrs = array_merge([
            'src' => $src,
            'alt' => $alt,
            'width' => $width,
            'height' => $height,
            'loading' => $priority ? 'eager' : 'lazy',
            'decoding' => 'async',
            'style' => 'max-width: 100%; height: auto;',
        ], $attributes);

        if ($priority) {
            $attrs['fetchpriority'] = 'high';
        }

        $htmlAttrs = [];
        foreach ($attrs as $name => $value) {
            if ($value === true) {
                $htmlAttrs[] = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            } elseif ($value !== false && $value !== null) {
                $htmlAttrs[] = htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"';
            }
        }

        return '<img ' . implode(' ', $htmlAttrs) . ' />';
    }
}
