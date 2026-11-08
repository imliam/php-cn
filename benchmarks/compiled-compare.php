<?php

$before = json_decode(file_get_contents($argv[1] ?? __DIR__ . '/results/compiled-before-off.json'), true, flags: JSON_THROW_ON_ERROR);
$after = json_decode(file_get_contents($argv[2] ?? __DIR__ . '/results/compiled-after-off.json'), true, flags: JSON_THROW_ON_ERROR);
foreach (['php_version', 'php_binary', 'os', 'opcache_mode', 'worker_ini', 'xdebug_mode_env', 'samples_per_case', 'loader_version', 'benchmark_sha256'] as $field) {
    if ($before[$field] !== $after[$field]) {
        throw new RuntimeException('Mismatched benchmark metadata: ' . $field);
    }
}
if (array_keys($before['cases']) !== array_keys($after['cases'])) {
    throw new RuntimeException('Reports contain different workloads.');
}

echo "| Workload | Before µs/op | After µs/op | Before / after |\n| --- | ---: | ---: | ---: |\n";
foreach ($before['cases'] as $case => $baseline) {
    $current = $after['cases'][$case];
    if ($baseline['iterations'] !== $current['iterations']) {
        throw new RuntimeException('Iteration count differs: ' . $case);
    }
    $checksums = array_unique(array_merge(array_column($baseline['samples'], 'checksum'), array_column($current['samples'], 'checksum')));
    if (count($checksums) !== 1) {
        throw new RuntimeException('Output checksums differ: ' . $case);
    }
    printf("| `%s` | %.3f | %.3f | %.2f× |\n", $case, $baseline['median_ns'] / 1000, $current['median_ns'] / 1000, $baseline['median_ns'] / $current['median_ns']);
}

echo "\n| Cold first merge memory, bytes | Before | After |\n| --- | ---: | ---: |\n";
foreach (['peak_used_memory_bytes', 'used_memory_bytes', 'peak_memory_bytes', 'opcache_source_shared_bytes', 'opcache_shared_used_bytes', 'opcache_interned_strings_used_bytes'] as $metric) {
    $values = [];
    foreach ([$before, $after] as $report) {
        $samples = array_column($report['cases']['cold_first_merge']['samples'], $metric);
        sort($samples);
        $count = count($samples);
        $values[] = $count % 2 === 0 ? ($samples[$count / 2 - 1] + $samples[$count / 2]) / 2 : $samples[intdiv($count, 2)];
    }
    printf("| `%s` | %.0f | %.0f |\n", $metric, ...$values);
}
