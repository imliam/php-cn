<?php

// Each adapter loads only the library's own src/ and psr/simple-cache, so
// Composer autoload files (and Laravel's framework for gehrisandro) cannot
// distort startup or memory. All construction goes through public APIs.
return [
    'php-cn' => [
        'psr4' => ['Cn\\' => 'src/', 'Psr\\SimpleCache\\' => 'vendor/psr/simple-cache/src/'],
        'files' => ['src/TailwindMerge.php'],
        'sources' => ['src'],
        'make' => static fn (array $config, ?object $cache): object => ($config === [] && $cache === null)
            ? Cn\TailwindMerge::instance()
            : tap(Cn\TailwindMerge::factory()->withConfiguration($config), $cache)->make(),
    ],
    'tales-from-a-dev' => [
        'psr4' => ['TalesFromADev\\TailwindMerge\\' => 'vendor/tales-from-a-dev/tailwind-merge-php/src/', 'Psr\\SimpleCache\\' => 'vendor/psr/simple-cache/src/'],
        'files' => [],
        'sources' => ['vendor/tales-from-a-dev/tailwind-merge-php/src'],
        'make' => static fn (array $config, ?object $cache): object => new TalesFromADev\TailwindMerge\TailwindMerge($config, $cache),
    ],
    'gehrisandro' => [
        'psr4' => ['TailwindMerge\\' => 'vendor/gehrisandro/tailwind-merge-php/src/', 'Psr\\SimpleCache\\' => 'vendor/psr/simple-cache/src/'],
        'files' => ['vendor/gehrisandro/tailwind-merge-php/src/TailwindMerge.php'],
        'sources' => ['vendor/gehrisandro/tailwind-merge-php/src'],
        // No cacheSize option exists; without a PSR-16 cache every merge is uncached.
        'make' => static function (array $config, ?object $cache): object {
            unset($config['cacheSize']);

            return ($config === [] && $cache === null)
                ? TailwindMerge\TailwindMerge::instance()
                : tap(TailwindMerge\TailwindMerge::factory()->withConfiguration($config), $cache)->make();
        },
    ],
];

function tap(object $factory, ?object $cache): object
{
    if ($cache !== null) {
        $factory->withCache($cache);
    }

    return $factory;
}
