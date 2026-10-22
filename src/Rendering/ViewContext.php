<?php

declare(strict_types=1);

namespace Next\Rendering;

/**
 * Context passed to pages, layouts, and components.
 */
class ViewContext
{
    /** @var array<string, mixed> */
    private array $params;

    /** @var array<string, mixed> */
    private array $searchParams;

    /** @var array{title?: string, description?: string, meta?: array<string, string>, links?: list<array<string, string>>} */
    private array $metadata = [];

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $searchParams
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        array $params = [],
        array $searchParams = [],
        array $metadata = [],
        private readonly string $path = '/'
    ) {
        $this->params = $params;
        $this->searchParams = $searchParams;
        $this->metadata = $metadata;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getParam(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function getSearchParams(): array
    {
        return $this->searchParams;
    }

    public function getSearchParam(string $key, mixed $default = null): mixed
    {
        return $this->searchParams[$key] ?? $default;
    }

    public function setTitle(string $title, bool $force = false): self
    {
        if ($force || !isset($this->metadata['title'])) {
            $this->metadata['title'] = $title;
        }
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->metadata['title'] ?? null;
    }

    public function setDescription(string $description, bool $force = false): self
    {
        if ($force || !isset($this->metadata['description'])) {
            $this->metadata['description'] = $description;
        }
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->metadata['description'] ?? null;
    }

    public function addMeta(string $name, string $content): self
    {
        $this->metadata['meta'][$name] = $content;
        return $this;
    }

    public function setMetadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Generate HTML tags for metadata (<title>, <meta>).
     */
    public function renderHead(): string
    {
        $html = [];

        if (isset($this->metadata['title'])) {
            $html[] = '<title>' . htmlspecialchars($this->metadata['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</title>';
        }

        if (isset($this->metadata['description'])) {
            $html[] = '<meta name="description" content="' . htmlspecialchars($this->metadata['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
        }

        if (!empty($this->metadata['meta'])) {
            foreach ($this->metadata['meta'] as $name => $content) {
                $attr = str_starts_with($name, 'og:') ? 'property' : 'name';
                $html[] = '<meta ' . $attr . '="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" content="' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '">';
            }
        }

        return implode("\n    ", $html);
    }
}
