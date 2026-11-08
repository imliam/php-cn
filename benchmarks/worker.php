<?php

use Psr\SimpleCache\CacheInterface;
use Benchmark\Merger;

// An in-process PSR-16 adapter isolates merger overhead from network/disk costs.
final class BenchmarkCache implements CacheInterface
{
    private array $values = [];
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $this->values[$key] = $value;

        return true;
    }
    public function delete(string $key): bool
    {
        unset($this->values[$key]);

        return true;
    }
    public function clear(): bool
    {
        $this->values = [];

        return true;
    }
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        foreach ($keys as $key) {
            yield $key => $this->get($key, $default);
        }
    }
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }
}

$case = $options['worker'];
$iterations = (int) $options['iterations'];
$base = 'inline-flex items-center justify-center rounded-md px-2 py-1 text-sm font-medium bg-blue-500 text-white hover:bg-blue-600 focus:ring-2';
$override = 'px-4 py-2 bg-red-500 hover:bg-red-600';
$expected = 'inline-flex items-center justify-center rounded-md text-sm font-medium text-white focus:ring-2 px-4 py-2 bg-red-500 hover:bg-red-600';
$checksum = 0;

if ($case === 'cold_first_merge') {
    $started = hrtime(true);
    $merger = Merger::instance();
    $output = $merger->merge($base, $override);
    $elapsed = hrtime(true) - $started;
    if ($output !== $expected) {
        throw new RuntimeException('Unexpected merge output: ' . $output);
    }
    $checksum = strlen($output);
} else {
    $factory = Merger::factory();
    if ($case === 'external_cache_hits') {
        $factory->withCache(new BenchmarkCache());
    }
    if ($case === 'unique_strings' || $case === 'unique_arbitrary_values') {
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
    } elseif ($case === 'unique_strings' || $case === 'unique_arbitrary_values') {
        for ($index = 0; $index < $iterations; $index++) {
            $extra = $case === 'unique_strings' ? ' component-' . $index : ' w-[' . ($index + 100) . 'px] p-[' . ($index + 10) . 'px] [mask-type:luminance]';
            $inputs[] = [$base, $override . $extra];
        }
        $warmup = [[$base, $override]];
    } else {
        $input = $case === 'nested_array_inputs' ? [[$base, [$override]]] : [$base, $override];
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
    $started = hrtime(true);
    foreach ($inputs as $input) {
        $checksum += strlen($merger->merge(...$input));
    }
    $elapsed = hrtime(true) - $started;
}

echo json_encode([
    'elapsed_ns' => $elapsed,
    'nanoseconds_per_operation' => $elapsed / $iterations,
    'checksum' => $checksum,
    'peak_memory_bytes' => memory_get_peak_usage(true),
    'peak_used_memory_bytes' => memory_get_peak_usage(false),
    'loaded_merger_class' => (new ReflectionClass(Merger::class))->getName(),
    'loaded_merger_file' => (new ReflectionClass(Merger::class))->getFileName(),
], JSON_THROW_ON_ERROR);
