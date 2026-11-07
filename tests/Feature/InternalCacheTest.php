<?php

use Cn\TailwindMerge;

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

it('disables result and token caches with cacheSize zero', function () {
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

    expect($calls)->toBe(2);
});

it('reuses the default merger while factories create independent instances', function () {
    expect(TailwindMerge::instance())->toBe(TailwindMerge::instance())
        ->not->toBe(TailwindMerge::factory()->make());
});
