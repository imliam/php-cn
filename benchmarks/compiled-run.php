<?php

// Each sample runs in a fresh process. Run with XDEBUG_MODE=off.
$options = getopt('', ['root:', 'label:', 'output:', 'samples:', 'worker:', 'iterations:', 'opcache:']);
$root = realpath($options['root'] ?? dirname(__DIR__));
if ($root === false || ! is_file($root . '/src/TailwindMerge.php') || ! is_file($root . '/vendor/psr/simple-cache/src/CacheInterface.php')) {
    throw new RuntimeException('The benchmark root must contain src/TailwindMerge.php and vendor/psr/simple-cache.');
}

$opcache = $options['opcache'] ?? 'off';
if (! in_array($opcache, ['off', 'on'], true)) {
    throw new RuntimeException('--opcache must be off or on.');
}
$cases = [
    'cold_first_merge' => 1,
    'cold_custom_config' => 1,
    'unique_strings' => 1000,
    'unique_arbitrary_values' => 1000,
    'long_arbitrary_value' => 100,
    'repeated_default' => 20000,
    'recurring_1024' => 8192,
    'custom_unique' => 1000,
];

if (isset($options['worker'])) {
    $precompiledSourceCount = 0;
    if ($opcache === 'on') {
        $status = opcache_get_status(false);
        if (! ($status['opcache_enabled'] ?? false) || ini_get('opcache.file_update_protection') !== '0') {
            throw new RuntimeException('OPcache must be enabled with file_update_protection=0.');
        }
        $sources = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS));
        foreach ($sources as $source) {
            if ($source->isFile() && $source->getExtension() === 'php') {
                $path = $source->getPathname();
                if (! opcache_compile_file($path) || ! opcache_is_script_cached($path)) {
                    throw new RuntimeException('Failed to precompile ' . $path);
                }
                $precompiledSourceCount++;
            }
        }
        unset($sources, $source, $path, $status);
    }
    require __DIR__ . '/bootstrap.php';
    // Exclude temporary precompilation allocations from request memory peaks.
    memory_reset_peak_usage();
    require __DIR__ . '/compiled-worker.php';
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
    'opcache_mode' => $opcache,
    'opcache_method' => 'All src PHP files compiled without execution before bootstrap; cold first-use timer follows bootstrap; peak reset before worker.',
    'worker_ini' => ['opcache.enable_cli' => $opcache === 'on' ? '1' : '0', 'opcache.jit' => 'disable', 'opcache.jit_buffer_size' => '0', 'opcache.file_update_protection' => '0', 'opcache.validate_timestamps' => '0'],
    'xdebug_mode_env' => getenv('XDEBUG_MODE'),
    'samples_per_case' => $samples,
    'loader_version' => 'standalone-psr4-v2-multi-namespace',
    'benchmark_sha256' => [
        'compiled-run.php' => hash_file('sha256', __FILE__),
        'compiled-worker.php' => hash_file('sha256', __DIR__ . '/compiled-worker.php'),
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
        $command = [PHP_BINARY, '-d', 'opcache.enable_cli=' . ($opcache === 'on' ? '1' : '0'), '-d', 'opcache.file_update_protection=0', '-d', 'opcache.validate_timestamps=0', '-d', 'opcache.jit=disable', '-d', 'opcache.jit_buffer_size=0', __FILE__, '--opcache=' . $opcache, '--root=' . $root, '--worker=' . $case, '--iterations=' . $iterations];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException($stderr);
        }
        if ($stderr !== '') {
            throw new RuntimeException($stderr);
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
    fprintf(STDERR, "%s: %.3f µs/op\n", $case, $median / 1000);
}

$json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
if (isset($options['output'])) {
    file_put_contents($options['output'], $json);
} else {
    echo $json;
}
