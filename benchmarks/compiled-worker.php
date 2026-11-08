<?php

use Benchmark\Merger;

$case = $options['worker'];
$iterations = (int) $options['iterations'];
$base = 'inline-flex items-center justify-center rounded-md px-2 py-1 text-sm font-medium bg-blue-500 text-white hover:bg-blue-600 focus:ring-2';
$override = 'px-4 py-2 bg-red-500 hover:bg-red-600';
$expected = 'inline-flex items-center justify-center rounded-md text-sm font-medium text-white focus:ring-2 px-4 py-2 bg-red-500 hover:bg-red-600';
$checksum = 0;
if ($case === 'cold_custom_config') {
    $override .= ' custom-a custom-b';
    $expected .= ' custom-b';
}

if ($case === 'cold_first_merge' || $case === 'cold_custom_config') {
    $started = hrtime(true);
    $merger = $case === 'cold_first_merge'
        ? Merger::instance()
        : Merger::factory()->withConfiguration(['classGroups' => ['custom' => ['custom-a', 'custom-b']]])->make();
    $output = $merger->merge($base, $override);
    $elapsed = hrtime(true) - $started;
    if ($output !== $expected) {
        throw new RuntimeException('Unexpected merge output: ' . $output);
    }
    $checksum = strlen($output);
} else {
    $factory = Merger::factory();
    if ($case === 'custom_unique') {
        $factory->withConfiguration(['cacheSize' => 0, 'classGroups' => ['custom' => ['custom-a', 'custom-b']]]);
    }
    if ($case === 'unique_strings' || $case === 'unique_arbitrary_values' || $case === 'long_arbitrary_value') {
        $factory->withConfiguration(['cacheSize' => 0]);
    }
    $merger = $factory->make();
    $inputs = [];
    $warmup = [];
    if (str_starts_with($case, 'recurring_')) {
        $size = (int) substr($case, strlen('recurring_'));
        for ($index = 0; $index < $size; $index++) {
            $warmup[] = [$base, $override . ' component-' . $index];
        }
        for ($index = 0; $index < $iterations; $index++) {
            $inputs[] = $warmup[$index % $size];
        }
    } elseif ($case === 'long_arbitrary_value') {
        $input = [$base, $override . ' bg-[url(https://example.test/' . str_repeat('a-', 5000) . 'image.png)]'];
        $inputs = array_fill(0, $iterations, $input);
        $warmup = [$input];
    } elseif ($case === 'unique_strings' || $case === 'unique_arbitrary_values' || $case === 'custom_unique') {
        for ($index = 0; $index < $iterations; $index++) {
            $extra = $case !== 'unique_arbitrary_values' ? ' component-' . $index : ' w-[' . ($index + 100) . 'px] p-[' . ($index + 10) . 'px] [mask-type:luminance]';
            $inputs[] = [$base, $override . $extra . ($case === 'custom_unique' ? ' custom-a custom-b' : '')];
        }
        $warmup = [[$base, $override]];
    } else {
        $input = [$base, $override];
        $inputs = array_fill(0, $iterations, $input);
        $warmup = [$input];
    }
    // Prime all recurring keys twice so admission-based caches can populate.
    foreach ([0, 1] as $pass) {
        foreach ($warmup as $input) {
            $merger->merge(...$input);
        }
    }
    if ($merger->merge($base, $override) !== $expected) {
        throw new RuntimeException('Shared Tailwind 3/4 fixture produced an unexpected result.');
    }
    if ($case === 'custom_unique' && $merger->merge('custom-a custom-b') !== 'custom-b') {
        throw new RuntimeException('Custom configuration did not resolve its own group.');
    }
    $started = hrtime(true);
    foreach ($inputs as $input) {
        $checksum += strlen($merger->merge(...$input));
    }
    $elapsed = hrtime(true) - $started;
}

$peakAllocated = memory_get_peak_usage(true);
$peakUsed = memory_get_peak_usage(false);
$used = memory_get_usage(false);
$opcacheStatus = function_exists('opcache_get_status') ? opcache_get_status(true) : false;
$sourceSharedBytes = 0;
foreach (($opcacheStatus['scripts'] ?? []) as $path => $script) {
    if (str_starts_with($path, $root . '/src/')) {
        $sourceSharedBytes += $script['memory_consumption'];
    }
}

echo json_encode([
    'elapsed_ns' => $elapsed,
    'nanoseconds_per_operation' => $elapsed / $iterations,
    'checksum' => $checksum,
    'peak_memory_bytes' => $peakAllocated,
    'peak_used_memory_bytes' => $peakUsed,
    'used_memory_bytes' => $used,
    'opcache_enabled' => $opcacheStatus['opcache_enabled'] ?? false,
    'opcache_cached_scripts' => $opcacheStatus['opcache_statistics']['num_cached_scripts'] ?? 0,
    'opcache_precompiled_source_count' => $precompiledSourceCount,
    'opcache_shared_used_bytes' => $opcacheStatus['memory_usage']['used_memory'] ?? 0,
    'opcache_source_shared_bytes' => $sourceSharedBytes,
    'opcache_interned_strings_used_bytes' => $opcacheStatus['interned_strings_usage']['used_memory'] ?? 0,
    'loaded_source_files' => array_values(array_filter(get_included_files(), static fn (string $path): bool => str_starts_with($path, $root . '/src/'))),
    'loaded_merger_class' => (new ReflectionClass(Merger::class))->getName(),
    'loaded_merger_file' => (new ReflectionClass(Merger::class))->getFileName(),
], JSON_THROW_ON_ERROR);
