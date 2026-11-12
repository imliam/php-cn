<?php

use Cn\TailwindMerge;

it('does not alter non-tailwind classes', function (string $input, string $output) {
    expect(TailwindMerge::instance()->merge($input))
        ->toBe($output);
})->with([
    ['non-tailwind-class inline block', 'non-tailwind-class block'],
    ['inline block inline-1', 'block inline-1'],
    ['inline block i-inline', 'block i-inline'],
    ['focus:inline focus:block focus:inline-1', 'focus:block focus:inline-1'],
]);

it('keeps only the last copy of repeated non-tailwind classes', function (string $input, string $output) {
    expect(TailwindMerge::instance()->merge($input))
        ->toBe($output);
})->with([
    ['foo bar foo', 'bar foo'],
    ['foo foo foo', 'foo'],
    ['p-2 foo p-4 foo', 'p-4 foo'],
    ['btn btn active', 'btn active'],
    ['foo hover:foo foo', 'hover:foo foo'],
]);

it('drops repeated non-tailwind classes across arguments', function () {
    expect(cn('btn', ['btn' => true, 'active' => true]))
        ->toBe('btn active');
});
