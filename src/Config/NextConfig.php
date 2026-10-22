<?php

declare(strict_types=1);

namespace Next\Config;

class NextConfig
{
    /**
     * @param array{
     *   basePath?: string,
     *   trailingSlash?: bool,
     *   headers?: list<array{source: string, headers: array<string, string>}>,
     *   redirects?: list<array{source: string, destination: string, permanent?: bool}>,
     *   rewrites?: list<array{source: string, destination: string}>,
     *   env?: array<string, string>,
     *   revalidate?: int
     * } $config
     */
    public function __construct(private array $config = []) {}

    public static function loadFromFile(string $filePath): self
    {
        if (!file_exists($filePath)) {
            return new self([]);
        }

        $config = require $filePath;
        return new self(is_array($config) ? $config : []);
    }

    public function getBasePath(): string
    {
        return $this->config['basePath'] ?? '';
    }

    public function hasTrailingSlash(): bool
    {
        return (bool) ($this->config['trailingSlash'] ?? false);
    }

    /**
     * @return list<array{source: string, headers: array<string, string>}>
     */
    public function getHeaders(): array
    {
        return $this->config['headers'] ?? [];
    }

    /**
     * @return list<array{source: string, destination: string, permanent?: bool}>
     */
    public function getRedirects(): array
    {
        return $this->config['redirects'] ?? [];
    }

    /**
     * @return list<array{source: string, destination: string}>
     */
    public function getRewrites(): array
    {
        return $this->config['rewrites'] ?? [];
    }

    public function getEnv(): array
    {
        return $this->config['env'] ?? [];
    }

    public function getRevalidateDefault(): int
    {
        return (int) ($this->config['revalidate'] ?? 0);
    }

    public function isSwupEnabled(): bool
    {
        $swup = $this->config['swup'] ?? true;
        if (is_array($swup)) {
            return (bool) ($swup['enabled'] ?? true);
        }
        return (bool) $swup;
    }

    /**
     * @return array{
     *   enabled: bool,
     *   animation: string,
     *   containers: list<string>,
     *   cache: bool,
     *   preload: bool
     * }
     */
    public function getSwupConfig(): array
    {
        $default = [
            'enabled' => true,
            'animation' => 'fade',
            'containers' => ['#swup', 'main'],
            'cache' => true,
            'preload' => true,
        ];

        $configured = $this->config['swup'] ?? [];
        if (is_array($configured)) {
            return array_merge($default, $configured);
        }

        return $default;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
}
