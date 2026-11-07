<?php

use Cn\Support\Config;

test('default config has correct types', function () {
    $defaultConfig = Config::getDefaultConfig();

    expect($defaultConfig)
        ->not->toHaveKey('nonExistent')
        ->cacheSize->toBe(500)
        ->classGroups->display->{0}->toBe('block')
        ->classGroups->overflow->{0}->overflow->{0}->toBe('auto')
        ->classGroups->overflow->{0}->not->toHaveKey('nonExistent');
});

test('numeric string class group names work in custom configuration', function () {
    $merge = \Cn\TailwindMerge::factory()->withConfiguration([
        'classGroups' => ['0' => ['one', 'two']],
    ])->make();

    expect($merge->merge('one two'))->toBe('two')
        ->and($merge->merge('one two one'))->toBe('one');
});
