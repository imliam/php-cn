<?php

use Cn\Support\ClassMap;
use Cn\Support\Config;
use Cn\Support\TailwindClassParser;

it('builds shared paths with terminal classes, theme values and fallback validators', function () {
    $config = [
        'theme' => ['spacing' => ['compact', 'roomy']],
        'classGroups' => [
            'display' => ['block', 'inline-block'],
            'space' => [['space' => ['', Config::fromTheme('spacing')]]],
            'space-x' => [['space' => [['x' => ['2', '4']]]]],
            'custom' => [['space' => [fn (string $value): bool => str_starts_with($value, 'custom-')]]],
        ],
    ];
    $map = ClassMap::create($config);

    expect($map->classGroupId)->toBeNull()
        ->and($map->nextPart['block']->classGroupId)->toBe('display')
        ->and($map->nextPart['inline']->nextPart['block']->classGroupId)->toBe('display')
        ->and($map->nextPart['space']->classGroupId)->toBe('space')
        ->and($map->nextPart['space']->nextPart['compact']->classGroupId)->toBe('space')
        ->and($map->nextPart['space']->nextPart['x']->nextPart[2]->classGroupId)->toBe('space-x');

    $parser = new TailwindClassParser($config);
    expect($parser->parse('space-custom-token')->classGroupId)->toBe('custom')
        ->and($parser->parse('space-x-4')->classGroupId)->toBe('space-x')
        ->and($parser->parse('space-unknown')->classGroupId)->toBeNull();
});

it('classifies named containers separately from unnamed container types', function () {
    $parser = new TailwindClassParser(Config::getDefaultConfig());

    expect($parser->parse('@container/card')->classGroupId)
        ->toBe($parser->parse('@container-normal/card')->classGroupId)
        ->toBe($parser->parse('@container/sidebar')->classGroupId)
        ->not->toBe($parser->parse('@container')->classGroupId);
});
