<?php

use Cn\TailwindMerge;
use Tests\Support\FakeCache;

it('does cache the result', function () {
    $cache = new FakeCache();

    $twMerge = TailwindMerge::factory()->withCache($cache)->make();

    expect($twMerge->merge('text-red-500 text-green-500'))
        ->toBe('text-green-500');

    expect($cache)
        ->hits->toBe(0)
        ->misses->toBe(1);

    expect($twMerge->merge('text-red-500 text-green-500'))
        ->toBe('text-green-500');

    expect($cache)
        ->hits->toBe(1)
        ->misses->toBe(1);

    expect($twMerge->merge('text-red-500 text-green-500 h-4'))
        ->toBe('text-green-500 h-4');

    expect($cache)
        ->hits->toBe(1)
        ->misses->toBe(2);

    expect($twMerge->merge('text-red-500 text-green-500 h-4'))
        ->toBe('text-green-500 h-4');

    expect($cache)
        ->hits->toBe(2)
        ->misses->toBe(2);
});
