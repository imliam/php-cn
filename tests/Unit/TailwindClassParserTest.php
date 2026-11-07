<?php

use Cn\Support\TailwindClassParser;

function parserConfiguration(array $overrides = []): array
{
    return array_replace([
        'prefix' => null,
        'theme' => [],
        'classGroups' => [
            'display' => ['block', 'hidden'],
            'p' => [['p' => ['1', '2', '3']]],
            'font-size' => [['text' => ['sm', 'lg']]],
            'leading' => [['leading' => ['6', '8']]],
            'w' => [['w' => ['1/2', '1/3']]],
            'variable' => [['bg' => [fn (string $value): bool => str_starts_with($value, '(')]]],
            'container-type' => ['@container'],
            'named-container' => ['@container/sidebar'],
        ],
        'orderSensitiveModifiers' => ['before', '*', '**'],
        'postfixLookupClassGroups' => ['container-type'],
    ], $overrides);
}

it('parses important markers and adjusts leading-important postfix offsets', function (string $input, string $base, bool $postfix) {
    $parsed = (new TailwindClassParser(parserConfiguration()))->parse($input);

    expect($parsed->hasImportantModifier)->toBeTrue()
        ->and($parsed->baseClassName)->toBe($base)
        ->and($parsed->hasPostfixModifier)->toBe($postfix)
        ->and($parsed->classGroupId)->toBe('font-size');
})->with([
    ['text-lg!', 'text-lg', false],
    ['!text-lg', 'text-lg', false],
    ['hover:text-lg/8!', 'text-lg/8', true],
    ['hover:!text-lg/8', 'text-lg/8', true],
]);

it('falls back to full class names containing slashes', function () {
    $parser = new TailwindClassParser(parserConfiguration());

    expect($parser->parse('w-1/2')->classGroupId)->toBe('w')
        ->and($parser->parse('w-1/2')->hasPostfixModifier)->toBeFalse()
        ->and($parser->parse('unknown/thing')->classGroupId)->toBeNull();
});

it('allows explicit groups for named container classes', function () {
    $parsed = (new TailwindClassParser(parserConfiguration()))->parse('@container/sidebar');

    expect($parsed->classGroupId)->toBe('named-container')
        ->and($parsed->hasPostfixModifier)->toBeFalse();
});

it('keeps separators and slashes inside nested arbitrary syntax', function (string $input, string $base, string $group) {
    $parsed = (new TailwindClassParser(parserConfiguration()))->parse($input);

    expect($parsed->modifiers)->toBe(['hover'])
        ->and($parsed->baseClassName)->toBe($base)
        ->and($parsed->classGroupId)->toBe($group)
        ->and($parsed->hasPostfixModifier)->toBeFalse();
})->with([
    ['hover:bg-(color:--brand/foo)', 'bg-(color:--brand/foo)', 'variable'],
    ['hover:[background:url(https://example.test/a)]', '[background:url(https://example.test/a)]', 'arbitrary..background'],
]);

it('sorts modifiers only within order-sensitive boundaries', function (string $input, string $modifierId) {
    expect((new TailwindClassParser(parserConfiguration()))->parse($input)->modifierId)->toBe($modifierId);
})->with([
    ['hover:focus:p-1', 'focus:hover'],
    ['hover:before:focus:p-1', 'hover:before:focus'],
    ['focus:hover:*:active:disabled:p-1', 'focus:hover:*:active:disabled'],
    ['hover:[&>div]:focus:p-1', 'hover:[&>div]:focus'],
    ['hover:**:focus:p-1', 'hover:**:focus'],
]);

it('requires the v4 prefix at the start of the entire class', function () {
    $parser = new TailwindClassParser(parserConfiguration(['prefix' => 'tw']));

    expect($parser->parse('tw:hover:p-1!')->classGroupId)->toBe('p')
        ->and($parser->parse('tw:hover:p-1!')->modifierId)->toBe('hover!')
        ->and($parser->parse('tw:hover:p-1!')->originalClassName)->toBe('tw:hover:p-1!')
        ->and($parser->parse('hover:tw:p-1')->classGroupId)->toBeNull()
        ->and($parser->parse('p-1')->classGroupId)->toBeNull()
        ->and($parser->parse('[color:red]')->classGroupId)->toBeNull()
        ->and($parser->parse('tw:[color:red]')->classGroupId)->toBe('arbitrary..color');
});

it('retains hyphen-prefix configuration compatibility', function () {
    $parser = new TailwindClassParser(parserConfiguration(['prefix' => 'tw-']));

    expect($parser->parse('hover:tw-p-1')->classGroupId)->toBe('p')
        ->and($parser->parse('p-1')->classGroupId)->toBeNull();
});

it('keeps separator and modifier settings local to the parser', function () {
    $first = new TailwindClassParser(parserConfiguration(['separator' => '__', 'orderSensitiveModifiers' => ['hover']]));
    $second = new TailwindClassParser(parserConfiguration());

    expect($first->parse('hover__focus__p-1')->modifierId)->toBe('hover:focus')
        ->and($second->parse('hover:focus:p-1')->modifierId)->toBe('focus:hover')
        ->and($first->parse('hover__focus__bg-(color:--a/b)')->classGroupId)->toBe('variable');
});

it('does not classify unknown classes or invalid arbitrary properties', function (string $input) {
    expect((new TailwindClassParser(parserConfiguration()))->parse($input)->classGroupId)->toBeNull();
})->with(['custom', '[custom]', '[:red]', '', ']hover:p-1', ')hover:p-1']);

it('rejects an empty modifier separator', function () {
    new TailwindClassParser(parserConfiguration(['separator' => '']));
})->throws(InvalidArgumentException::class);
