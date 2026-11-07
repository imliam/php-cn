<?php

use Cn\TailwindMerge;
use Cn\Validators\NumberValidator;
use Tests\Support\FakeCache;

it('keeps custom conflicts on the instance that configured them', function () {
    $custom = TailwindMerge::factory()->withConfiguration([
        'conflictingClassGroups' => ['p' => ['font-weight']],
    ])->make();
    $default = TailwindMerge::instance();

    expect($custom->merge('font-bold p-4'))->toBe('p-4')
        ->and($default->merge('font-bold p-4'))->toBe('font-bold p-4');
});

it('keeps separators on the instance that configured them', function () {
    $custom = TailwindMerge::factory()->withConfiguration(['separator' => '__'])->make();
    $default = TailwindMerge::instance();

    expect($custom->merge('hover__p-2 hover__p-4'))->toBe('hover__p-4')
        ->and($default->merge('hover:p-2 hover:p-4'))->toBe('hover:p-4');
});

it('does not share cached results across configurations', function () {
    $cache = new FakeCache();
    $custom = TailwindMerge::factory()->withCache($cache)->withConfiguration([
        'conflictingClassGroups' => ['p' => ['font-weight']],
    ])->make();
    $default = TailwindMerge::factory()->withCache($cache)->make();

    expect($default->merge('font-bold p-4'))->toBe('font-bold p-4')
        ->and($custom->merge('font-bold p-4'))->toBe('p-4');
});

it('keeps unknown classes including duplicates and zero', function () {
    expect(TailwindMerge::instance()->merge('custom custom 0 p-2 p-4'))
        ->toBe('custom custom 0 p-4');
});

it('shares external results between equivalent serializable configurations', function () {
    $cache = new FakeCache();
    $first = TailwindMerge::factory()->withCache($cache)->withConfiguration(['prefix' => 'tw'])->make();
    $second = TailwindMerge::factory()->withCache($cache)->withConfiguration(['prefix' => 'tw'])->make();

    expect($first->merge('tw:p-2 tw:p-4'))->toBe('tw:p-4')
        ->and($second->merge('tw:p-2 tw:p-4'))->toBe('tw:p-4')
        ->and($cache->hits)->toBe(1)
        ->and($cache->misses)->toBe(1);
});

it('isolates external results for anonymous validators', function () {
    $cache = new FakeCache();
    $first = TailwindMerge::factory()->withCache($cache)->withConfiguration([
        'classGroups' => ['custom' => [['custom' => [fn (string $value): bool => $value !== '']]]],
    ])->make();
    $second = TailwindMerge::factory()->withCache($cache)->withConfiguration([
        'classGroups' => ['custom' => [['custom' => [fn (string $value): bool => $value === 'one']]]],
    ])->make();

    expect($first->merge('custom-one custom-two'))->toBe('custom-two')
        ->and($second->merge('custom-one custom-two'))->toBe('custom-one custom-two');
});

it('separates custom group IDs from modifier IDs', function () {
    $merge = TailwindMerge::factory()->withConfiguration([
        'classGroups' => ['hoverfoo' => ['first'], 'foo' => ['second']],
    ])->make();

    expect($merge->merge('first hover:second'))->toBe('first hover:second');
});

it('distinguishes validator identities from literal class definitions in external cache keys', function () {
    $cache = new FakeCache();
    $validator = TailwindMerge::factory()->withCache($cache)->withConfiguration([
        'classGroups' => ['numbers' => [NumberValidator::validate(...)]],
    ])->make();
    $literal = TailwindMerge::factory()->withCache($cache)->withConfiguration([
        'classGroups' => ['numbers' => [['callable' => [NumberValidator::class, 'validate']]]],
    ])->make();

    expect($validator->merge('1 2'))->toBe('2')
        ->and($literal->merge('1 2'))->toBe('1 2');
});
