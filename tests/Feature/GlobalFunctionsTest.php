<?php

it('provides global cn and clsx helpers without function imports', function () {
    expect(\cn('p-2', ['p-4' => true, 'hidden' => false]))->toBe('p-4')
        ->and(\clsx('p-2', ['p-4' => true, 'hidden' => false]))->toBe('p-2 p-4')
        ->and(\cn())->toBe('')
        ->and(\clsx())->toBe('');
});

it('forwards named arguments and associative argument unpacking through global helpers', function () {
    expect(\cn(base: 'p-2', overrides: ['p-4' => true]))->toBe('p-4')
        ->and(\clsx(classes: 'p-2'))->toBe('p-2')
        ->and(\cn(...['base' => 'p-2', 'overrides' => ['p-4' => true]]))->toBe('p-4')
        ->and(\clsx(...['base' => 'p-2', 'overrides' => ['p-4' => true]]))->toBe('p-2 p-4');
});

it('guards each global helper independently while preserving the namespaced API', function (bool $existingCn, bool $existingClsx) {
    $code = <<<'PHP'
        if ($argv[2] === 'yes') {
            function cn(mixed ...$inputs): string {
                return 'existing global cn';
            }
        }

        if ($argv[3] === 'yes') {
            function clsx(mixed ...$inputs): string {
                return 'existing global clsx';
            }
        }

        require $argv[1];

        echo json_encode([
            'globalCn' => \cn('p-2', ['p-4' => true]),
            'globalClsx' => \clsx('p-2', ['p-4' => true]),
            'namespacedCn' => Cn\cn('p-2', ['p-4' => true]),
            'namespacedClsx' => Cn\clsx('p-2', ['p-4' => true]),
        ], JSON_THROW_ON_ERROR);
        PHP;

    // An argv array bypasses shell parsing; each scenario gets a fresh function table.
    $process = proc_open([
        PHP_BINARY,
        '-r',
        $code,
        dirname(__DIR__, 2) . '/vendor/autoload.php',
        $existingCn ? 'yes' : 'no',
        $existingClsx ? 'yes' : 'no',
    ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start the isolated helper test.');
    }

    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    expect($exitCode)->toBe(0, $errors)
        ->and($errors)->toBe('')
        ->and(json_decode($output, true, flags: JSON_THROW_ON_ERROR))->toBe([
            'globalCn' => $existingCn ? 'existing global cn' : 'p-4',
            'globalClsx' => $existingClsx ? 'existing global clsx' : 'p-2 p-4',
            'namespacedCn' => 'p-4',
            'namespacedClsx' => 'p-2 p-4',
        ]);
})->with([
    'only cn already exists' => [true, false],
    'only clsx already exists' => [false, true],
    'both helpers already exist' => [true, true],
]);
