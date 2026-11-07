<?php

use Cn\Support\Config;
use Cn\TailwindMerge;

it('keeps compiled default merging identical to the runtime trie for every upstream fixture', function () {
    $fixtures = json_decode(file_get_contents(__DIR__ . '/../Fixtures/tailwind-4-merges.json'), true, flags: JSON_THROW_ON_ERROR);
    $configuration = Config::getMergedConfig(['cacheSize' => 0]);
    $compiled = TailwindMerge::factory()->withConfiguration(['cacheSize' => 0])->make();
    $dynamic = new TailwindMerge($configuration);

    foreach ($fixtures['cases'] as [$group, $input, $expected]) {
        $actual = $compiled->merge($input);

        expect($actual)->toBe($dynamic->merge($input), "$group runtime parity: $input")
            ->and($actual)->toBe($expected, "$group upstream parity: $input");
    }
});

it('keeps compiled and runtime parsing identical for syntax and lookup edge cases', function (string $input, string $expected) {
    $compiled = TailwindMerge::factory()->withConfiguration(['cacheSize' => 0])->make();
    $dynamic = new TailwindMerge(Config::getMergedConfig(['cacheSize' => 0]));

    expect($compiled->merge($input))->toBe($expected)
        ->and($compiled->merge($input))->toBe($dynamic->merge($input));
})->with([
    ['-translate-x-1/2 -translate-x-full', '-translate-x-full'],
    ['-inset-1 -inset-2', '-inset-2'],
    ['w-1/2 w-1/3', 'w-1/3'],
    ['leading-6 text-lg/8', 'text-lg/8'],
    ['!leading-6 !text-lg/8', '!text-lg/8'],
    ['!text-red-500 !text-lg/8', '!text-red-500 !text-lg/8'],
    ['@container/foo @container/bar @container-normal', '@container/bar @container-normal'],
    ['@container/foo @container/foo', '@container/foo'],
    ['@container @container-normal', '@container-normal'],
    ['unknown unknown 0 p-1 p-2', 'unknown unknown 0 p-2'],
    ['[color:red] [color:blue]', '[color:blue]'],
    ['bg-(color:--brand) bg-(color:--accent)', 'bg-(color:--accent)'],
    ['hover:[&>p]:p-1 [&>p]:hover:p-2', 'hover:[&>p]:p-1 [&>p]:hover:p-2'],
    ['hover:before:p-1 before:hover:p-2', 'hover:before:p-1 before:hover:p-2'],
    ['focus:hover:p-1 hover:focus:p-2', 'hover:focus:p-2'],
    [']hover:p-1 ]hover:p-1 )hover:p-2 )hover:p-2', ']hover:p-1 ]hover:p-1 )hover:p-2 )hover:p-2'],
    ['text-shadow-brand text-shadow-sm text-shadow-[color:red]', 'text-shadow-sm text-shadow-[color:red]'],
    ['bg-linear-brand bg-linear-to-r bg-linear-45', 'bg-linear-brand bg-linear-45'],
]);

it('supports prefix and separator settings with default lookup tables', function (array $configuration, string $input, string $expected) {
    $compiled = TailwindMerge::factory()->withConfiguration($configuration)->make();
    $dynamic = new TailwindMerge(Config::getMergedConfig($configuration));

    expect($compiled->merge($input))->toBe($expected)
        ->and($compiled->merge($input))->toBe($dynamic->merge($input));
})->with([
    [['prefix' => 'tw'], 'tw:bg-linear-brand tw:bg-linear-to-r tw:bg-linear-45', 'tw:bg-linear-brand tw:bg-linear-45'],
    [['prefix' => 'tw'], 'tw:hover:p-1! tw:hover:p-2! p-1 p-2', 'tw:hover:p-2! p-1 p-2'],
    [['separator' => '__'], 'hover__p-1 hover__p-2', 'hover__p-2'],
    [['prefix' => 'tw', 'separator' => '__'], 'tw__hover__p-1 tw__hover__p-2', 'tw__hover__p-2'],
    [['prefix' => 'tw-'], 'tw-bg-linear-brand tw-bg-linear-to-r tw-bg-linear-45', 'tw-bg-linear-brand tw-bg-linear-45'],
]);

it('keeps custom validators and their precedence when using the factory', function () {
    $calls = [];
    $merge = TailwindMerge::factory()->withConfiguration([
        'cacheSize' => 0,
        'classGroups' => [
            'custom-gradient' => [['bg-linear' => [function (string $value) use (&$calls): bool {
                $calls[] = $value;

                return str_starts_with($value, 'brand-');
            }]]],
        ],
    ])->make();

    expect($merge->merge('bg-linear-brand-one bg-linear-brand-two bg-red-500'))->toBe('bg-linear-brand-two bg-red-500')
        ->and($calls)->toContain('brand-one', 'brand-two');
});

it('keeps custom theme values and conflict rules when using the factory', function () {
    $theme = TailwindMerge::factory()->withConfiguration(['theme' => ['text' => ['massive']]])->make();
    $conflicts = TailwindMerge::factory()->withConfiguration(['conflictingClassGroups' => ['p' => ['font-weight']]])->make();
    $modifiers = TailwindMerge::factory()->withConfiguration(['orderSensitiveModifiers' => ['custom']])->make();
    $default = TailwindMerge::factory()->make();

    expect($theme->merge('text-sm text-massive'))->toBe('text-massive')
        ->and($default->merge('text-sm text-massive'))->toBe('text-sm text-massive')
        ->and($conflicts->merge('font-bold p-4'))->toBe('p-4')
        ->and($default->merge('font-bold p-4'))->toBe('font-bold p-4')
        ->and($modifiers->merge('custom:hover:p-1 hover:custom:p-2'))->toBe('custom:hover:p-1 hover:custom:p-2')
        ->and($default->merge('custom:hover:p-1 hover:custom:p-2'))->toBe('hover:custom:p-2');
});

it('merges long arbitrary URLs consistently through both lookup implementations', function () {
    $first = 'hover:bg-[url(https://example.test/' . str_repeat('path-', 5000) . 'one.png)]';
    $last = 'hover:bg-[url(https://example.test/' . str_repeat('path-', 5000) . 'two.png)]';
    $input = $first . ' ' . $last;
    $compiled = TailwindMerge::factory()->withConfiguration(['cacheSize' => 0])->make();
    $dynamic = new TailwindMerge(Config::getMergedConfig(['cacheSize' => 0]));

    expect($compiled->merge($input))->toBe($last)
        ->and($compiled->merge($input))->toBe($dynamic->merge($input));
});
