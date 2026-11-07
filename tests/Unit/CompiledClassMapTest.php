<?php

use Cn\Support\CompiledClassMap;
use Cn\Support\Config;
use Cn\Support\TailwindClassParser;

it('looks up exact classes before validators and falls back through shorter prefixes', function (string $class, ?string $group) {
    expect((new CompiledClassMap())->getClassGroupId($class))->toBe($group);
})->with([
    ['block', 'display'],
    ['flex', 'display'],
    ['font-bold', 'font-weight'],
    ['font-custom', 'font-family'],
    ['bg-linear-to-r', 'bg-image'],
    ['bg-linear-45', 'bg-image'],
    ['bg-linear-brand', 'bg-color'],
    ['bg-linear', 'bg-color'],
    ['bg-', 'bg-color'],
    ['text-shadow-sm', 'text-shadow'],
    ['text-shadow-brand', 'text-shadow-color'],
    ['text-shadow-[0_1px_2px_red]', 'text-shadow'],
    ['text-shadow-[color:red]', 'text-shadow-color'],
    ['text-[length:12px]', 'font-size'],
    ['text-[color:red]', 'text-color'],
    ['text-(length:--size)', 'font-size'],
    ['shadow-sm', 'shadow'],
    ['shadow-brand', 'shadow-color'],
    ['@container', 'container-type'],
    ['@container/sidebar', 'container-named'],
    ['@container-normal/sidebar', 'container-named'],
    ['p-nonsense', null],
    ['unknown', null],
    ['0', null],
    ['', null],
]);

it('keeps compiled parser classifications identical to the runtime trie for every fixture token', function () {
    $fixtures = json_decode(file_get_contents(__DIR__ . '/../Fixtures/tailwind-4-merges.json'), true, flags: JSON_THROW_ON_ERROR);
    $configuration = Config::getDefaultConfig();
    $compiled = new TailwindClassParser($configuration, new CompiledClassMap());
    $dynamic = new TailwindClassParser($configuration);
    $tokens = [];

    foreach ($fixtures['cases'] as [, $input]) {
        foreach (explode(' ', $input) as $token) {
            $tokens[$token] = true;
        }
    }

    foreach ($tokens as $token => $_) {
        expect($compiled->parse((string) $token))->toEqual($dynamic->parse((string) $token), (string) $token);
    }
});

it('keeps validator prefix boundaries intact for long values', function (string $class, ?string $group) {
    $compiled = new CompiledClassMap();
    $dynamic = new TailwindClassParser(Config::getDefaultConfig());

    expect($compiled->getClassGroupId($class))->toBe($group)
        ->and($compiled->getClassGroupId($class))->toBe($dynamic->parse($class)->classGroupId);
})->with([
    ['backdrop-brightness-75', 'backdrop-brightness'],
    ['backdrop-brightness-(--brightness)', 'backdrop-brightness'],
    ['backdrop-hue-rotate-[125deg]', 'backdrop-hue-rotate'],
    ['backdrop-brightness', null],
    ['backdrop-brightness-', null],
    ['backdrop-brightness-extra-75', null],
    ['aaaaaaaaaaaaaaaaaaaaaaaa-p-1', null],
    ['@container-size/sidebar-with-a-very-long-name', 'container-named'],
]);

it('classifies long arbitrary URLs without changing validator precedence', function () {
    $class = 'bg-[url(https://example.test/' . str_repeat('path-', 5000) . 'image.png)]';
    $compiled = new CompiledClassMap();
    $dynamic = new TailwindClassParser(Config::getDefaultConfig());

    expect($compiled->getClassGroupId($class))->toBe('bg-image')
        ->and($compiled->getClassGroupId($class))->toBe($dynamic->parse($class)->classGroupId);
});
