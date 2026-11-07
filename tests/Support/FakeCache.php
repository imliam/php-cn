<?php

namespace Tests\Support;

use DateInterval;
use Exception;
use Psr\SimpleCache\CacheInterface;

class FakeCache implements CacheInterface
{
    private array $cache = [];

    public int $hits = 0;

    public int $misses = 0;

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->cache)) {
            $this->hits++;

            return $this->cache[$key];
        }

        $this->misses++;

        return $default;
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $this->cache[$key] = $value;

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->cache[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->cache = [];

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        throw new Exception('Not implemented');
    }

    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        throw new Exception('Not implemented');
    }

    public function deleteMultiple(iterable $keys): bool
    {
        throw new Exception('Not implemented');
    }

    public function has(string $key): bool
    {
        $found = array_key_exists($key, $this->cache);

        if (! $found) {
            $this->misses++;
        }

        return $found;
    }
}
