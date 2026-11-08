<?php

// Each sample runs in a fresh process. Run with XDEBUG_MODE=off.
$options = getopt('', ['root:', 'label:', 'output:', 'samples:', 'worker:', 'iterations:']);
$root = realpath($options['root'] ?? dirname(__DIR__));
if ($root === false || ! is_file($root . '/src/TailwindMerge.php') || ! is_file($root . '/vendor/psr/simple-cache/src/CacheInterface.php')) {
    throw new RuntimeException('The benchmark root must contain src/TailwindMerge.php and vendor/psr/simple-cache.');
}

$cases = [
    'cold_first_merge' => 1,
    'unique_strings' => 200,
    'unique_arbitrary_values' => 200,
    'repeated_default' => 2000,
    'recurring_128' => 512,
    'recurring_1024' => 2048,
    'nested_array_inputs' => 2000,
    'external_cache_hits' => 20000,
];

if (isset($options['worker'])) {
    require __DIR__ . '/bootstrap.php';
    require __DIR__ . '/worker.php';
    exit;
}

$samples = max(1, (int) ($options['samples'] ?? 7));
$result = [
    'label' => $options['label'] ?? 'working-tree',
    'recorded_at' => gmdate('c'),
    'root' => $root,
    'php_version' => PHP_VERSION,
    'php_binary' => PHP_BINARY,
    'os' => php_uname(),
    'opcache_cli' => ini_get('opcache.enable_cli'),
    'jit' => ini_get('opcache.jit'),
    'xdebug_mode_env' => getenv('XDEBUG_MODE'),
    'samples_per_case' => $samples,
    'loader_version' => 'standalone-psr4-v2-multi-namespace',
    'benchmark_sha256' => [
        'run.php' => hash_file('sha256', __FILE__),
        'worker.php' => hash_file('sha256', __DIR__ . '/worker.php'),
        'bootstrap.php' => hash_file('sha256', __DIR__ . '/bootstrap.php'),
    ],
    'source_sha256' => [],
    'cases' => [],
];
$sources = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS));
foreach ($sources as $source) {
    if ($source->isFile()) {
        $result['source_sha256'][substr($source->getPathname(), strlen($root) + 1)] = hash_file('sha256', $source->getPathname());
    }
}
ksort($result['source_sha256']);

foreach ($cases as $case => $iterations) {
    $measurements = [];
    for ($sample = 0; $sample < $samples; $sample++) {
        $command = [PHP_BINARY, '-d', 'opcache.enable_cli=0', __FILE__, '--root=' . $root, '--worker=' . $case, '--iterations=' . $iterations];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException($stderr);
        }
        $measurements[] = json_decode($stdout, true, flags: JSON_THROW_ON_ERROR);
    }
    $times = array_column($measurements, 'nanoseconds_per_operation');
    sort($times);
    $count = count($times);
    $median = $count % 2 === 0 ? ($times[$count / 2 - 1] + $times[$count / 2]) / 2 : $times[intdiv($count, 2)];
    $result['cases'][$case] = ['iterations' => $iterations, 'median_ns' => $median, 'min_ns' => min($times), 'max_ns' => max($times), 'samples' => $measurements];
    fprintf(STDERR, "%s: %.3f µs/op\n", $case, $median / 1000);
}

$json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
if (isset($options['output'])) {
    file_put_contents($options['output'], $json);
} else {
    echo $json;
}
