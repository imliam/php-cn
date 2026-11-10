<?php

// Usage: php compare.php <results-dir> <off|on>
[$_, $directory, $mode] = $argv + [null, __DIR__ . '/results', 'off'];
$libraries = ['php-cn', 'tales-from-a-dev', 'gehrisandro'];
$reports = [];
foreach ($libraries as $library) {
    $reports[$library] = json_decode(file_get_contents("$directory/$library-opcache-$mode.json"), true, flags: JSON_THROW_ON_ERROR);
}
$reference = $reports['php-cn'];
foreach ($reports as $library => $report) {
    if ($report['php_version'] !== $reference['php_version'] || $report['benchmark_sha256'] !== $reference['benchmark_sha256'] || $report['samples_per_case'] !== $reference['samples_per_case']) {
        throw new RuntimeException("$library was measured with a different environment or harness.");
    }
    foreach ($report['cases'] as $case => $result) {
        if (array_column($result['samples'], 'checksum') !== array_column($reference['cases'][$case]['samples'], 'checksum')) {
            throw new RuntimeException("$library $case output checksum differs from php-cn.");
        }
    }
}

// Round to what a reader can compare at a glance.
$number = static fn (float $value): string => number_format($value, match (true) {
    $value >= 100 => 0,
    $value >= 10 => 1,
    default => 2,
});

echo '| Workload | ' . implode(' | ', $libraries) . " |\n";
echo '| --- |' . str_repeat(' ---: |', count($libraries)) . "\n";
foreach (array_keys($reference['cases']) as $case) {
    $times = array_map(static fn (array $report): float => $report['cases'][$case]['median_ns'] / 1000, $reports);
    $fastest = min($times);
    $cells = array_map(static fn (float $time): string => $time === $fastest ? '**' . $number($time) . '**' : $number($time) . ' (' . $number($time / $fastest) . '×)', $times);
    echo "| `$case` | " . implode(' | ', $cells) . " |\n";
}

echo "\n| Cold default merger, bytes | " . implode(' | ', $libraries) . " |\n";
echo '| --- |' . str_repeat(' ---: |', count($libraries)) . "\n";
foreach (['peak_used_memory_bytes' => 'Peak PHP-used memory', 'used_memory_after_bytes' => 'PHP-used memory after merge'] as $key => $label) {
    $cells = array_map(static function (array $report) use ($key): string {
        $values = array_column($report['cases']['cold_first_merge']['samples'], $key);
        sort($values);

        return number_format($values[intdiv(count($values), 2)]);
    }, $reports);
    echo "| $label | " . implode(' | ', $cells) . " |\n";
}
