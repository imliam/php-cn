<?php

namespace Cn;

use Psr\SimpleCache\CacheInterface;
use Cn\Support\CompiledClassMap;
use Cn\Support\Config;

final class Factory
{
    /**
     * @var array<string, mixed>
     */
    private array $additionalConfiguration = [];

    private ?CacheInterface $cache = null;

    /**
     * Override the default configuration.
     *
     * @param  array<string, mixed>  $configuration
     */
    public function withConfiguration(array $configuration): self
    {
        $this->additionalConfiguration = $configuration;

        return $this;
    }

    public function withCache(CacheInterface $cache): self
    {
        $this->cache = $cache;

        return $this;
    }

    /**
     * Creates a new TailwindMerge instance.
     */
    public function make(): TailwindMerge
    {
        if (array_diff_key($this->additionalConfiguration, ['cacheSize' => true, 'prefix' => true, 'separator' => true]) === []) {
            $prefix = $this->additionalConfiguration['prefix'] ?? null;
            if (! is_string($prefix) || ! str_ends_with($prefix, '-')) {
                $classMap = new CompiledClassMap();
                $config = Config::merge($classMap->configuration(), $this->additionalConfiguration);

                return new TailwindMerge($config, $this->cache, $classMap);
            }
        }

        $config = Config::getMergedConfig($this->additionalConfiguration);

        return new TailwindMerge($config, $this->cache);
    }
}
