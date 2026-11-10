<?php

use Psr\SimpleCache\CacheInterface;

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
// Classes valid in both Tailwind 3 and 4, with variants, negatives, arbitrary values and conflicts.
$mix = [
    ['flex flex-col gap-2 p-4 md:flex-row md:gap-4', 'p-6 md:gap-8'],
    ['rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900', 'shadow-md dark:bg-black'],
    ['text-sm font-medium leading-6 text-gray-900 hover:text-gray-700 focus-visible:outline-2', 'text-base text-blue-600'],
    ['absolute inset-0 -z-10 h-full w-full object-cover opacity-50', 'relative z-10 opacity-75'],
    ['grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-4 xl:gap-x-8', 'gap-4 lg:grid-cols-3'],
    ['mx-auto max-w-7xl px-4 sm:px-6 lg:px-8', 'max-w-2xl px-2'],
    ['w-[calc(100%-2rem)] h-[42px] bg-[#1da1f2] text-[13px] [mask-type:luminance]', 'w-full bg-[rgb(0,0,0)]'],
    ['inline-block truncate align-middle -mt-1 ml-2 tracking-tight', 'mt-0 tracking-wide whitespace-normal'],
    ['ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600 disabled:cursor-not-allowed disabled:opacity-50', 'ring-0 focus:ring-red-500'],
    ['transition-colors duration-200 ease-in-out hover:bg-gray-50 active:bg-gray-100 group-hover:underline', 'duration-300 hover:bg-gray-100'],
    ['overflow-hidden overflow-x-auto scroll-smooth snap-x snap-mandatory', 'overflow-visible snap-y'],
    ['font-sans text-left text-xl font-bold sm:text-2xl lg:text-4xl', 'font-serif text-center font-semibold'],
];
$checksum = 0;

$make = $adapter['make'];
if ($case === 'cold_first_merge') {
    $started = hrtime(true);
    $merger = $make([], null);
    $output = $merger->merge($base, $override);
    $elapsed = hrtime(true) - $started;
    if ($output !== $expected) {
        throw new RuntimeException('Unexpected merge output: ' . $output);
    }
    $checksum = strlen($output);
    $outputs = [$output];
} else {
    $uncached = in_array($case, ['unique_strings', 'unique_arbitrary_values', 'realistic_mix'], true);
    $merger = $make($uncached ? ['cacheSize' => 0] : [], $case === 'external_cache_hits' ? new BenchmarkCache() : null);
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
    } elseif ($case === 'unique_tokens_default') {
        // Every arbitrary-value token is new, so neither result nor per-class memos can hit.
        for ($index = 0; $index < $iterations; $index++) {
            $n = $index + 1000;
            $inputs[] = [$base, $override . " w-[{$n}px] h-[{$n}px] p-[{$n}px] m-[{$n}px] gap-[{$n}px] text-[{$n}px] bg-[#{$n}] border-[{$n}px] rounded-[{$n}px] top-[{$n}px]"];
        }
        $warmup = [[$base, $override]];
    } elseif (in_array($case, ['unique_strings', 'unique_arbitrary_values', 'unique_strings_default', 'unique_arbitrary_values_default'], true)) {
        for ($index = 0; $index < $iterations; $index++) {
            $extra = str_starts_with($case, 'unique_strings') ? ' component-' . $index : ' w-[' . ($index + 100) . 'px] p-[' . ($index + 10) . 'px] [mask-type:luminance]';
            $inputs[] = [$base, $override . $extra];
        }
        $warmup = [[$base, $override]];
    } elseif ($case === 'realistic_mix') {
        for ($index = 0; $index < $iterations; $index++) {
            $inputs[] = $mix[$index % count($mix)];
        }
        $warmup = $mix;
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
    $outputs = $case === 'realistic_mix' ? array_map(static fn (array $input): string => $merger->merge(...$input), $mix) : [];
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
    'outputs' => $outputs,
    'peak_memory_bytes' => memory_get_peak_usage(true),
    'peak_used_memory_bytes' => memory_get_peak_usage(false),
    'used_memory_after_bytes' => memory_get_usage(false),
    'opcache_enabled' => function_exists('opcache_get_status') && (opcache_get_status(false)['opcache_enabled'] ?? false),
    'loaded_merger_class' => get_class($merger),
], JSON_THROW_ON_ERROR);
