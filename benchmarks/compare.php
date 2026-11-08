<?php

$before = json_decode(file_get_contents($argv[1] ?? __DIR__ . '/results/before.json'), true, flags: JSON_THROW_ON_ERROR);
$after = json_decode(file_get_contents($argv[2] ?? __DIR__ . '/results/after.json'), true, flags: JSON_THROW_ON_ERROR);

foreach (['php_version', 'php_binary', 'os', 'opcache_cli', 'jit', 'xdebug_mode_env', 'loader_version', 'benchmark_sha256'] as $field) {
    if ($before[$field] !== $after[$field]) {
        throw new RuntimeException('Benchmark environments differ: ' . $field);
    }
}

echo "| Workload | Before µs/op | After µs/op | Speedup |\n";
echo "| --- | ---: | ---: | ---: |\n";
foreach ($before['cases'] as $name => $old) {
    $new = $after['cases'][$name] ?? throw new RuntimeException('Missing workload: ' . $name);
    if ($old['iterations'] !== $new['iterations']) {
        throw new RuntimeException('Iteration counts differ: ' . $name);
    }
    $checksums = array_unique([...array_column($old['samples'], 'checksum'), ...array_column($new['samples'], 'checksum')]);
    if (count($checksums) !== 1) {
        throw new RuntimeException('Output-length checksums differ: ' . $name);
    }
    printf("| `%s` | %.3f | %.3f | %.2f× |\n", $name, $old['median_ns'] / 1000, $new['median_ns'] / 1000, $old['median_ns'] / $new['median_ns']);
}
