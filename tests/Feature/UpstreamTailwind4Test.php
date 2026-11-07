<?php

use Cn\TailwindMerge;

test('matches the pinned upstream implementation across every Tailwind 4 class group', function () {
    $fixtures = json_decode(file_get_contents(__DIR__ . '/../Fixtures/tailwind-4-merges.json'), true, flags: JSON_THROW_ON_ERROR);
    $merge = TailwindMerge::factory()->withConfiguration(['cacheSize' => 0])->make();

    expect($fixtures['classGroups'])->toBe(379);

    foreach ($fixtures['cases'] as [$group, $input, $expected]) {
        expect($merge->merge($input))->toBe($expected, "$group: $input");
    }
});

test('matches the pinned upstream validator test cases', function () {
    $fixtures = json_decode(file_get_contents(__DIR__ . '/../Fixtures/tailwind-4-validators.json'), true, flags: JSON_THROW_ON_ERROR);

    foreach ($fixtures['cases'] as [$validator, $input, $expected]) {
        $class = 'Cn\\Validators\\' . $validator;

        expect($class::validate($input))->toBe($expected, "$validator: $input");
    }
});
