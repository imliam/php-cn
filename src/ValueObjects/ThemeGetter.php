<?php

declare(strict_types=1);

namespace Cn\ValueObjects;

class ThemeGetter
{
    public function __construct(
        public readonly string $key
    ) {}

    /**
     * @param  array<array-key, mixed>  $theme
     * @return array<array-key, mixed>
     */
    public function get(array $theme): array
    {
        $value = $theme[$this->key] ?? [];

        return is_array($value) ? $value : [];
    }
}
