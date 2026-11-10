# Benchmarks

| Report | What it compares |
| --- | --- |
| [Library comparison](libraries/README.md) | php-cn against other PHP Tailwind merge libraries |
| [Tailwind 3 to 4 rewrite](#tailwind-3-to-4-rewrite) | php-cn before and after the Tailwind 4 rewrite |
| [Compiled defaults](compiled.md) | Runtime configuration against generated lookup tables |

Every report uses PHP 8.5.8 on macOS ARM64 with Xdebug and JIT off, and takes the median of seven fresh PHP processes. The JSON files in [results](results) hold the raw samples.

## Tailwind 3 to 4 rewrite

Measured on 5 September 2026 with OPcache off. "Before" is commit `400040c`. Times are µs per merge.

| Workload | Before | After | Speedup |
| --- | ---: | ---: | ---: |
| First merge in a new process | 2,079 | 2,189 | 0.95× |
| Unique strings, no cache | 939 | 20.6 | 46× |
| Unique arbitrary values, no cache | 969 | 24.7 | 39× |
| Same input repeated | 890 | 0.28 | 3,184× |
| 128 inputs, repeating | 945 | 0.30 | 3,123× |
| 1,024 inputs, repeating | 922 | 4.41 | 209× |
| Nested array input | 901 | 0.40 | 2,278× |
| PSR-16 cache hit | 0.50 | 0.34 | 1.5× |

- Uncached merges got 39–46× faster.
- Repeated inputs got about 3,000× faster, mostly from the new built-in result cache.
- Startup got 5% slower and uses 24% more memory, because the Tailwind 4 configuration is larger.

These figures describe php-cn as it was then. Later changes made it faster again; see the [library comparison](libraries/README.md) for current numbers.

### Reproduce

Run from the project root after `composer install`:

```sh
baseline_root="$(bash benchmarks/snapshot.sh 400040c89bc958ed130699ee4db0213f689cd498)"
XDEBUG_MODE=off php -d opcache.enable_cli=0 benchmarks/run.php \
  --root="$baseline_root" --label=baseline-400040c \
  --output=/tmp/tailwind-merge-before.json
XDEBUG_MODE=off php -d opcache.enable_cli=0 benchmarks/run.php \
  --label=working-tree --output=/tmp/tailwind-merge-after.json
php benchmarks/compare.php /tmp/tailwind-merge-before.json /tmp/tailwind-merge-after.json
```

Current revisions keep the parsed-class cache at `cacheSize=0`, so the "no cache" rows no longer measure parsing alone.
