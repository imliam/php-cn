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

    expect($twMerge->merge('text-red-500 text-green-500 h-4'))
        ->toBe('text-green-500 h-4');

    expect($cache)
        ->hits->toBe(0)
        ->misses->toBe(2);

    // Another merger sharing the cache, such as one in the next request, reads its results.
    $otherTwMerge = TailwindMerge::factory()->withCache($cache)->make();

    expect($otherTwMerge->merge('text-red-500 text-green-500'))
        ->toBe('text-green-500');

    expect($cache)
        ->hits->toBe(1)
        ->misses->toBe(2);
});

it('serves repeated results from memory before the external cache', function () {
    $cache = new FakeCache();

    $twMerge = TailwindMerge::factory()->withCache($cache)->make();

    expect($twMerge->merge('text-red-500 text-green-500'))->toBe('text-green-500')
        ->and($twMerge->merge('text-red-500 text-green-500'))->toBe('text-green-500');

    expect($cache)
        ->hits->toBe(0)
        ->misses->toBe(1);
});
