<?php

namespace Cn\Support;

/**
 * Two generations keep recently used entries without maintaining a linked list.
 *
 * @template TValue
 */
final class BoundedCache
{
    /** @var array<string, TValue> */
    private array $current = [];

    /** @var array<string, TValue> */
    private array $previous = [];

    public function __construct(private readonly int $size) {}

    /** @return TValue|null */
    public function get(string $key): mixed
    {
        if (isset($this->current[$key])) {
            return $this->current[$key];
        }

        if (isset($this->previous[$key])) {
            $value = $this->previous[$key];
            $this->set($key, $value);

            return $value;
        }

        return null;
    }

    /** @param TValue $value */
    public function set(string $key, mixed $value): void
    {
        if ($this->size <= 0) {
            return;
        }

        $this->current[$key] = $value;
        if (count($this->current) >= $this->size) {
            $this->previous = $this->current;
            $this->current = [];
        }
    }
}
