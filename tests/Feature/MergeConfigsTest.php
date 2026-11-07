<?php

use Cn\Support\Config;

it('extends lists and overrides scalars without changing the default configuration', function () {
    $default = Config::getDefaultConfig();
    $config = Config::getMergedConfig([
        'cacheSize' => 0,
        'prefix' => 'tw',
        'theme' => ['text' => ['hero']],
        'conflictingClassGroups' => ['p' => ['font-weight']],
    ]);

    expect($config['cacheSize'])->toBe(0)
        ->and($config['prefix'])->toBe('tw')
        ->and($config['theme']['text'])->toBe([...$default['theme']['text'], 'hero'])
        ->and($config['conflictingClassGroups']['p'])->toBe([...$default['conflictingClassGroups']['p'], 'font-weight'])
        ->and(Config::getDefaultConfig())->toBe($default);
});
