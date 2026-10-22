<?php

declare(strict_types=1);

namespace Next\Component;

/**
 * Head component for page-level metadata customization.
 */
class Head
{
    /**
     * @param array{
     *   title?: string,
     *   description?: string,
     *   keywords?: string,
     *   openGraph?: array<string, string>,
     *   meta?: array<string, string>
     * } $tags
     */
    public static function render(array $tags): string
    {
        $html = [];

        if (isset($tags['title'])) {
            $html[] = '<title>' . htmlspecialchars($tags['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</title>';
        }

        if (isset($tags['description'])) {
            $html[] = '<meta name="description" content="' . htmlspecialchars($tags['description'], ENT_QUOTES, 'UTF-8') . '">';
        }

        if (isset($tags['keywords'])) {
            $html[] = '<meta name="keywords" content="' . htmlspecialchars($tags['keywords'], ENT_QUOTES, 'UTF-8') . '">';
        }

        if (isset($tags['openGraph']) && is_array($tags['openGraph'])) {
            foreach ($tags['openGraph'] as $prop => $val) {
                $html[] = '<meta property="og:' . htmlspecialchars($prop, ENT_QUOTES, 'UTF-8') . '" content="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '">';
            }
        }

        if (isset($tags['meta']) && is_array($tags['meta'])) {
            foreach ($tags['meta'] as $name => $val) {
                $html[] = '<meta name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" content="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '">';
            }
        }

        return implode("\n    ", $html);
    }
}
