# Compiled default tables experiment

This experiment compares the Tailwind 4 implementation before and after adding generated default lookup tables. The original [Tailwind 3 to 4 benchmark](README.md) and its reports remain unchanged.

## Measured results

Measured on 5 September 2026. Each table compares the same Tailwind 4 configuration and merge behavior.

### CLI OPcache disabled

Raw samples: [before](results/compiled-before-off.json), [after](results/compiled-after-off.json).

| Workload | Before µs/op | After µs/op | Before / after |
| --- | ---: | ---: | ---: |
| `cold_first_merge` | 2254.834 | 1258.625 | 1.79× |
| `cold_custom_config` | 2204.125 | 2242.459 | 0.98× |
| `unique_strings` | 20.573 | 22.119 | 0.93× |
| `unique_arbitrary_values` | 25.099 | 26.543 | 0.95× |
| `long_arbitrary_value` | 411.062 | 411.803 | 1.00× |
| `repeated_default` | 0.286 | 0.288 | 0.99× |
| `recurring_1024` | 4.493 | 4.705 | 0.95× |
| `custom_unique` | 22.562 | 22.624 | 1.00× |

### Precompiled source with OPcache enabled

Raw samples: [before](results/compiled-before-on.json), [after](results/compiled-after-on.json).

| Workload | Before µs/op | After µs/op | Before / after |
| --- | ---: | ---: | ---: |
| `cold_first_merge` | 653.708 | 125.250 | 5.22× |
| `cold_custom_config` | 657.584 | 641.166 | 1.03× |
| `unique_strings` | 19.981 | 20.108 | 0.99× |
| `unique_arbitrary_values` | 23.923 | 23.663 | 1.01× |
| `long_arbitrary_value` | 358.163 | 355.193 | 1.01× |
| `repeated_default` | 0.272 | 0.266 | 1.02× |
| `recurring_1024` | 4.436 | 4.484 | 0.99× |
| `custom_unique` | 21.689 | 21.812 | 0.99× |

Default initialization improves by **1.79× without OPcache** and **5.22× from precompiled source**. With OPcache disabled, the uncached ordinary-string row takes 7.5% longer, arbitrary values take 5.8% longer, and the recurring row takes 4.7% longer. With OPcache enabled, all warm rows are within about 2.2% of the baseline. Repeated result-cache hits and the long arbitrary URL case remain close in both modes. Extended configurations retain the runtime path and have similar timings.

These results support keeping the compiled defaults for lower initialization cost and memory. They do not establish a faster steady-state parser. Applications dominated by uncached parsing with OPcache disabled should account for the measured regression.

### Memory

| Cold default merger, bytes | Off before | Off after | On before | On after |
| --- | ---: | ---: | ---: | ---: |
| Peak PHP-used memory | 2,334,056 | 2,030,960 | 1,809,440 | 914,584 |
| PHP-used memory after merge | 2,223,440 | 1,545,464 | 1,804,088 | 808,608 |
| Peak PHP allocator reservation | 4,194,304 | 4,194,304 | 4,194,304 | 4,194,304 |
| Cached library scripts, shared | 0 | 0 | 410,432 | 615,472 |
| Total OPcache used, shared | 0 | 0 | 9,710,720 | 9,905,584 |

Cold peak PHP-used memory falls **13.0% without OPcache** and **49.5% with precompiled source**. PHP-used memory retained after the merge falls 30.5% and 55.2%, respectively. The generated tables add **205,040 bytes, about 200 KiB, of cached library script memory**. The OPcache totals include precompiled source files that the default path does not use. Shared cache storage is separate from the request's PHP-used allocation; the tradeoff is less request memory and more cached code/data.

## Method

Each result is the median of seven isolated PHP processes on PHP 8.5.8, macOS ARM64. Xdebug and JIT are disabled. Both revisions use the same standalone [PSR-4 loader](bootstrap.php), workloads, worker flags, and timing boundaries. Reports record source and benchmark SHA-256 hashes, loaded source paths, checksums, raw samples, and memory counters. The [comparison script](compiled-compare.php) rejects mismatched environments, scripts, iterations, and output checksums.

The baseline was the uncommitted Tailwind 4 working tree when this experiment began, rather than the repository's older Tailwind 3 HEAD. Its source and PSR runtime dependency are preserved in [compiled-baseline-src.tar.gz](results/compiled-baseline-src.tar.gz), with their licenses. Extracting the archive provides the root expected by the benchmark. The after reports identify the implementation by source hashes.

There are two OPcache modes:

- `off` disables CLI OPcache. Cold timing includes reading and compiling the PHP files needed for the first merger, constructing it, and performing its first merge. The already-loaded `TailwindMerge.php`, autoloader registration, and PHP process startup are outside the timer.
- `on` first calls `opcache_compile_file()` for every PHP file in the revision's `src/`, outside the timer. It verifies that OPcache is enabled and every source file is cached. No merger, configuration, or lookup tree is initialized during this step. Cold timing then measures first initialization and merge from precompiled source. `opcache.file_update_protection=0` allows freshly generated files to enter the cache, and timestamp validation is disabled.

PHP documents [`opcache_compile_file()`](https://www.php.net/manual/en/function.opcache-compile-file.php) as compiling and caching a file without executing it. On the measured PHP version, it also registers some class declarations, which avoids some autoloader calls during first use. Consequently the `on` cold number measures initialization from precompiled source, not complete fresh-request latency under PHP-FPM. Every source file is precompiled, including files unused by the default merger, so its shared-memory total is an upper bound for these library files in this benchmark setup.

PHP-used peaks are reset after precompilation and bootstrap. This excludes temporary compiler allocations while retaining any surviving PHP allocations. The worker captures memory before allocating OPcache reporting arrays. `peak_used_memory_bytes` is PHP-used memory, `peak_memory_bytes` is the PHP allocator's reserved memory, and `opcache_source_shared_bytes` sums the library's cached-script memory. The reports also retain total shared OPcache usage and interned-string usage from [`opcache_get_status()`](https://www.php.net/manual/en/function.opcache-get-status.php). These counters measure different things; do not add interned strings to the total shared allocation or interpret PHP-used memory as process RSS.

Warm workloads construct one merger and prepare their input arrays outside the timer. The recurring set is warmed twice. Timed loops include argument unpacking and output-length checksum accounting. No empty-loop subtraction is applied.

| Workload | What it exercises |
| --- | --- |
| `cold_first_merge` | Default singleton construction and first component merge |
| `cold_custom_config` | First merger with an additional utility group, including its conflict resolution |
| `unique_strings` | 1,000 distinct component strings, result and token caches disabled |
| `unique_arbitrary_values` | 1,000 distinct arbitrary-value strings, both caches disabled |
| `long_arbitrary_value` | 100 parses of a background URL containing 5,000 hyphens, both caches disabled |
| `repeated_default` | 20,000 identical calls using the default result cache |
| `recurring_1024` | 8,192 calls cycling through 1,024 strings, exceeding result-cache capacity |
| `custom_unique` | 1,000 distinct strings with an additional utility group and caches disabled |

The custom rows exercise the runtime fallback for extended configuration. These are synthetic component inputs, not a production application's class corpus. Small sub-microsecond changes and small differences in the unchanged custom path should be treated as noise unless repeated measurements establish otherwise.

## Reproduce

The recorded results used loader `standalone-psr4-v1` and the earlier `TailwindMerge\` namespace. The current `standalone-psr4-v2-multi-namespace` harness supports both those snapshots and the package's `Cn\` namespace through a benchmark-only `Benchmark\Merger` alias created before timing. It records the actual merger class as well as its file. The package itself provides no legacy namespace alias.

Run both revisions with this updated harness. Its version and script hashes differ from the historical reports, so the comparator rejects mixing old and new reports. The commands below create fresh measurements; they do not overwrite the recorded experiment or claim results for the `cn()` helper.

From the project root with PHP and Composer dependencies installed:

```sh
compiled_baseline_root="$(mktemp -d /tmp/tailwind-compiled-baseline.XXXXXX)"
tar -xzf benchmarks/results/compiled-baseline-src.tar.gz -C "$compiled_baseline_root"

for mode in off on; do
  XDEBUG_MODE=off php benchmarks/compiled-run.php \
    --root="$compiled_baseline_root" --label=tailwind4-runtime-config \
    --opcache="$mode" --output="/tmp/compiled-before-$mode.json"
  XDEBUG_MODE=off php benchmarks/compiled-run.php \
    --label=compiled-default-tables --opcache="$mode" \
    --output="/tmp/compiled-after-$mode.json"
  php benchmarks/compiled-compare.php \
    "/tmp/compiled-before-$mode.json" "/tmp/compiled-after-$mode.json"
done
```

Each driver launches workers sequentially with explicit OPcache/JIT settings. Do not benchmark both revisions concurrently. `--samples=1` is useful for checking the setup, but the recorded comparison uses seven samples.
