<?php

declare(strict_types=1);

namespace Next\Routing;

/**
 * Value object representing a matched route in the file system.
 */
class RouteMatch
{
    /**
     * @param RouteType $type
     * @param string $filePath The absolute path to page.php or route.php
     * @param array<string, mixed> $params Dynamic route parameters (e.g. ['slug' => 'hello-world'])
     * @param string $routePattern The matched route pattern (e.g. '/blog/[slug]')
     * @param list<string> $layoutFiles Array of layout.php paths from root to leaf
     * @param ?string $loadingFile Path to closest loading.php
     * @param ?string $errorFile Path to closest error.php
     * @param ?string $notFoundFile Path to closest not-found.php
     */
    public function __construct(
        private readonly RouteType $type,
        private readonly string $filePath,
        private readonly array $params = [],
        private readonly string $routePattern = '/',
        private readonly array $layoutFiles = [],
        private readonly ?string $loadingFile = null,
        private readonly ?string $errorFile = null,
        private readonly ?string $notFoundFile = null,
    ) {}

    public function getType(): RouteType
    {
        return $this->type;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    public function getParam(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function getRoutePattern(): string
    {
        return $this->routePattern;
    }

    /**
     * @return list<string>
     */
    public function getLayoutFiles(): array
    {
        return $this->layoutFiles;
    }

    public function getLoadingFile(): ?string
    {
        return $this->loadingFile;
    }

    public function getErrorFile(): ?string
    {
        return $this->errorFile;
    }

    public function getNotFoundFile(): ?string
    {
        return $this->notFoundFile;
    }
}
