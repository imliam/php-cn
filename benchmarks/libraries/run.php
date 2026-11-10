<?php

// Compares php-cn with other PHP Tailwind merge libraries. Each sample runs in
// a fresh process. Run with XDEBUG_MODE=off.
$options = getopt('', ['library:', 'root:', 'output:', 'samples:', 'worker:', 'iterations:', 'opcache:']);
$adapters = require __DIR__ . '/adapters.php';
$library = $options['library'] ?? throw new RuntimeException('--library is required: ' . implode(', ', array_keys($adapters)));
$adapter = $adapters[$library] ?? throw new RuntimeException('Unknown library ' . $library);
$root = realpath($options['root'] ?? dirname(__DIR__, 2));
if ($root === false) {
    throw new RuntimeException('--root does not exist.');
}
$opcache = $options['opcache'] ?? 'off';
if (! in_array($opcache, ['off', 'on'], true)) {
    throw new RuntimeException('--opcache must be off or on.');
}

$cases = [
    'cold_first_merge' => 1,
    'unique_strings' => 1000,
    'unique_arbitrary_values' => 1000,
    'realistic_mix' => 1200,
    'unique_strings_default' => 1000,
    'unique_arbitrary_values_default' => 1000,
    'unique_tokens_default' => 1000,
    'repeated_default' => 20000,
    'recurring_128' => 4096,
    'recurring_1024' => 8192,
    'nested_array_inputs' => 20000,
    'external_cache_hits' => 20000,
];

$sourceFiles = [];
foreach ($adapter['sources'] as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $sourceFiles[] = $file->getPathname();
        }
    }
}
sort($sourceFiles);

if (isset($options['worker'])) {
    if ($opcache === 'on') {
        if (! (opcache_get_status(false)['opcache_enabled'] ?? false)) {
            throw new RuntimeException('OPcache is not enabled.');
        }
        foreach ($sourceFiles as $path) {
            if (! opcache_compile_file($path)) {
                throw new RuntimeException('Failed to precompile ' . $path);
            }
        }
    }
    spl_autoload_register(static function (string $class) use ($adapter, $root): void {
        foreach ($adapter['psr4'] as $prefix => $directory) {
            if (str_starts_with($class, $prefix)) {
                $file = $root . '/' . $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($file)) {
                    require $file;
                }
            }
        }
    });
    foreach ($adapter['files'] as $file) {
        require $root . '/' . $file;
    }
    memory_reset_peak_usage();
    require __DIR__ . '/worker.php';
    exit;
}

$samples = max(1, (int) ($options['samples'] ?? 7));
$result = [
    'library' => $library,
    'recorded_at' => gmdate('c'),
    'root' => $root,
    'php_version' => PHP_VERSION,
    'os' => php_uname(),
    'opcache_mode' => $opcache,
    'xdebug_mode_env' => getenv('XDEBUG_MODE'),
    'samples_per_case' => $samples,
    'benchmark_sha256' => [
        'run.php' => hash_file('sha256', __FILE__),
        'worker.php' => hash_file('sha256', __DIR__ . '/worker.php'),
        'adapters.php' => hash_file('sha256', __DIR__ . '/adapters.php'),
    ],
    'source_sha256' => array_combine(
        array_map(static fn (string $path): string => substr($path, strlen($root) + 1), $sourceFiles),
        array_map(static fn (string $path): string => hash_file('sha256', $path), $sourceFiles),
    ),
    'cases' => [],
];

foreach ($cases as $case => $iterations) {
    $measurements = [];
    for ($sample = 0; $sample < $samples; $sample++) {
        $command = [PHP_BINARY, '-d', 'opcache.enable_cli=' . ($opcache === 'on' ? '1' : '0'), '-d', 'opcache.file_update_protection=0', '-d', 'opcache.validate_timestamps=0', '-d', 'opcache.jit=disable', '-d', 'opcache.jit_buffer_size=0', __FILE__, '--library=' . $library, '--root=' . $root, '--opcache=' . $opcache, '--worker=' . $case, '--iterations=' . $iterations];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0 || $stderr !== '') {
            throw new RuntimeException($library . ' ' . $case . ': ' . $stderr . $stdout);
        }
        $measurement = json_decode($stdout, true, flags: JSON_THROW_ON_ERROR);
        if ($measurement['opcache_enabled'] !== ($opcache === 'on')) {
            throw new RuntimeException('Worker OPcache state does not match requested mode.');
        }
        $measurements[] = $measurement;
    }
    $times = array_column($measurements, 'nanoseconds_per_operation');
    sort($times);
    $count = count($times);
    $median = $count % 2 === 0 ? ($times[$count / 2 - 1] + $times[$count / 2]) / 2 : $times[intdiv($count, 2)];
    $result['cases'][$case] = ['iterations' => $iterations, 'median_ns' => $median, 'min_ns' => min($times), 'max_ns' => max($times), 'samples' => $measurements];
    fprintf(STDERR, "%s %s: %.3f µs/op\n", $library, $case, $median / 1000);
}

$json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
if (isset($options['output'])) {
    file_put_contents($options['output'], $json);
} else {
    echo $json;
}
