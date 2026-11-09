<?php

use Cn\TailwindMerge;
use Tests\Support\FakeCache;

it('reuses parsed tokens across different inputs', function () {
    $calls = 0;
    $merge = TailwindMerge::factory()->withConfiguration([
        'classGroups' => ['custom' => [['custom' => [function (string $value) use (&$calls): bool {
            $calls++;

            return $value !== '';
        }]]]],
    ])->make();

    expect($merge->merge('custom-one custom-two'))->toBe('custom-two')
        ->and($merge->merge('custom-one p-4'))->toBe('custom-one p-4')
        ->and($calls)->toBe(2);
});

it('keeps reusing parsed tokens with cacheSize zero', function () {
    $calls = 0;
    $merge = TailwindMerge::factory()->withConfiguration([
        'cacheSize' => 0,
        'classGroups' => ['custom' => [['custom' => [function (string $value) use (&$calls): bool {
            $calls++;

            return $value !== '';
        }]]]],
    ])->make();

    $merge->merge('custom-one');
    $merge->merge('custom-one');

    expect($calls)->toBe(1);
});

it('disables the in-memory result cache with cacheSize zero', function () {
    $cache = new FakeCache();
    $merge = TailwindMerge::factory()->withConfiguration(['cacheSize' => 0])->withCache($cache)->make();

    $merge->merge('text-red-500 text-green-500');
    $merge->merge('text-red-500 text-green-500');

    // Without the in-memory result cache, every repeat reaches the external cache.
    expect($cache)
        ->hits->toBe(1)
        ->misses->toBe(1);
});

it('reuses the default merger while factories create independent instances', function () {
    expect(TailwindMerge::instance())->toBe(TailwindMerge::instance())
        ->not->toBe(TailwindMerge::factory()->make());
});
