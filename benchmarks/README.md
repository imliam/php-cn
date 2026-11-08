# PHP merge benchmarks

These workloads compare this library before and after the Tailwind 4 upgrade. They do not compare PHP with JavaScript or reproduce the speedup advertised by `cn`.

## Measured results

Measured on 5 September 2026 with PHP 8.5.8 on macOS ARM64, with Xdebug and CLI OPcache disabled. Each value is the median of seven fresh-process samples. The baseline is commit `400040c89bc958ed130699ee4db0213f689cd498`; the updated implementation is identified by source hashes in [after.json](results/after.json). Raw baseline samples are in [before.json](results/before.json).

Both reports use the same standalone autoloader. An [initial baseline capture](results/before-initial.json) is retained for provenance, but is superseded by the measurements below. The first comparison mixed a standalone baseline loader with Composer development autoloading for the updated implementation, which distorted memory and startup comparisons.

| Workload | Before µs/op | After µs/op | Speedup |
| --- | ---: | ---: | ---: |
| `cold_first_merge` | 2078.875 | 2189.417 | 0.95× |
| `unique_strings` | 939.472 | 20.623 | 45.55× |
| `unique_arbitrary_values` | 969.289 | 24.738 | 39.18× |
| `repeated_default` | 889.521 | 0.279 | 3183.73× |
| `recurring_128` | 944.549 | 0.302 | 3122.58× |
| `recurring_1024` | 921.598 | 4.409 | 209.03× |
| `nested_array_inputs` | 901.067 | 0.395 | 2278.42× |
| `external_cache_hits` | 0.504 | 0.335 | 1.51× |

The uncached rows improve by 39–46×. The much larger repeated-input gains include a new default result cache; the original library only cached results when supplied with an external PSR-16 adapter. The 1,024-string workload exceeds the default result cache capacity and still benefits from reusing the parser and common parsed tokens. External PSR-16 hits improve by 1.51× in this in-memory fixture.

Cold startup regresses by about 5%, from 2.08 ms to 2.19 ms. Across the seven samples, cold times range from 2.01 to 2.15 ms before, and 2.11 to 2.46 ms after. Cold peak PHP-used memory increases from 2,063,808 to 2,560,864 bytes, about 1.97 to 2.44 MiB or 24%. Peak allocated memory is 4 MiB in every workload for both revisions. The larger Tailwind 4 configuration and retained lookup/cache structures trade memory and initial loading cost for subsequent merge speed. Exact cold figures are copied into [cold-memory.json](results/cold-memory.json) from the same benchmark samples; both runs use identical loading and memory accounting.

## Reproduce

The recorded results above used loader `standalone-psr4-v1`, before the package moved from `TailwindMerge\` to `Cn\`. The current harness uses `standalone-psr4-v2-multi-namespace`: it loads either namespace and creates a benchmark-only `Benchmark\Merger` alias before timing. Reports also record the actual merger class. This compatibility alias is not part of the package.

The commands below produce a fresh comparison using the updated harness. Rerun both revisions together; the comparator rejects a new report paired with a historical report because the loader version and script hashes differ. Existing recorded results remain unchanged and describe their original source snapshots.

Run from the project root after installing Composer dependencies:

```sh
baseline_root="$(bash benchmarks/snapshot.sh 400040c89bc958ed130699ee4db0213f689cd498)"
XDEBUG_MODE=off php -d opcache.enable_cli=0 benchmarks/run.php \
  --root="$baseline_root" --label=baseline-400040c \
  --output=/tmp/tailwind-merge-before.json
XDEBUG_MODE=off php -d opcache.enable_cli=0 benchmarks/run.php \
  --label=working-tree --output=/tmp/tailwind-merge-after.json
php benchmarks/compare.php /tmp/tailwind-merge-before.json /tmp/tailwind-merge-after.json
```

`--root` selects the source checkout. `snapshot.sh` archives the requested Git revision without changing the current checkout and copies the PSR Simple Cache runtime dependency. Both revisions use the exact same [standalone PSR-4 loader](bootstrap.php), loading only their own `src/` files and the runtime dependency. Composer autoload files are bypassed for both revisions so development dependencies cannot distort startup or memory measurements. Every sample records the actual loaded `TailwindMerge.php` path. Reports include the loader version and hashes of the benchmark scripts and all measured source files; comparison requires matching benchmark scripts and environment metadata.

Each workload runs seven independent PHP processes and reports the median, minimum, maximum, raw timings, output-length checksums, and peak used/allocated memory. Set `--samples=3` for a shorter run. Timing starts after registering the common autoloader and loading `TailwindMerge.php`. Cold timing includes the first merger construction and first merge, including lazy class/configuration loading, but excludes PHP process startup and autoload registration. Other workloads reuse one merger, prepare inputs outside the timer, and warm the chosen input set twice before measurement. OPcache CLI is disabled in workers and the commands disable Xdebug instrumentation. Timings include loop, argument unpacking, and output-length accounting overhead; no empty-loop subtraction is applied.

| Workload | What it measures |
| --- | --- |
| `cold_first_merge` | First `instance()` construction and first component merge in a fresh process |
| `unique_strings` | Unique component strings with `cacheSize=0`, so parser and result caches cannot hide merge costs |
| `unique_arbitrary_values` | Unique strings containing arbitrary values/properties, also with `cacheSize=0` |
| `repeated_default` | The same variadic component call with the default cache policy |
| `recurring_128` | A cyclic set of 128 component strings, within the default result cache capacity |
| `recurring_1024` | A cyclic set of 1,024 component strings, beyond the default result cache capacity |
| `nested_array_inputs` | A repeated nested array call, including flattening and the default result cache policy |
| `external_cache_hits` | A warmed in-memory PSR-16 adapter; excludes network and filesystem latency |

The fixtures use classes shared by Tailwind 3 and 4. Each process checks a representative conflict-resolution result before accepting its timing. Output-length checksums should also agree between the reports. Correctness of Tailwind 4 behavior is covered separately by the test suite. These are synthetic component workloads, not measurements from a production application's class corpus. Compare both uncached and cached rows; a faster cache hit alone does not establish a faster parser.

## Inspiration from cn

We inspected shadcn-ui/cn at commit [`1ca7cc0`](https://github.com/shadcn-ui/cn/tree/1ca7cc0fbb1e5a77a3e157799da68dbca7063521). Its [engine](https://github.com/shadcn-ui/cn/blob/1ca7cc0fbb1e5a77a3e157799da68dbca7063521/packages/cn/src/engine.ts) compiles configuration into lookup tables, memoizes token classification, and checks conflicts with a backward pass. Its [architecture notes](https://github.com/shadcn-ui/cn/blob/1ca7cc0fbb1e5a77a3e157799da68dbca7063521/docs/how-it-works.md) explain argument caching, admission-controlled string caching, and isolated-process benchmarks.

The useful PHP changes are to build configuration and the class trie once per merger, reuse parsed tokens, keep bounded result caches, and replace collection pipelines with a direct reverse loop. PHP's native array/string operations suit this implementation. A literal port of JavaScript typed arrays, character hashing, or argument-identity prediction would need its own evidence before being worth the added complexity.
