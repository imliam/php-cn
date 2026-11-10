# Library comparison

php-cn against two other PHP Tailwind merge libraries:

- [tales-from-a-dev/tailwind-merge-php](https://github.com/tales-from-a-dev/tailwind-merge-php) 0.5.1 (Tailwind 4)
- [gehrisandro/tailwind-merge-php](https://github.com/gehrisandro/tailwind-merge-php) 1.2.0 (Tailwind 3), the engine behind [tailwind-merge-laravel](https://github.com/gehrisandro/tailwind-merge-laravel) 1.4.0

Measured on 9 November 2026. All three libraries gave identical output for every workload. Times are µs per merge; lower is better. The fastest library in each row is bold, and the others show how many times slower they are.

## OPcache on

| Workload | php-cn | tales-from-a-dev | gehrisandro |
| --- | ---: | ---: | ---: |
| `cold_first_merge` | **126** | 196 (1.56×) | 932 (7.40×) |
| `unique_strings` | 3.55 (1.12×) | **3.17** | 837 (264×) |
| `unique_arbitrary_values` | 5.55 (1.05×) | **5.31** | 884 (166×) |
| `realistic_mix` | 1.53 (1.11×) | **1.38** | 555 (402×) |
| `unique_strings_default` | 3.56 (1.02×) | **3.49** | 841 (241×) |
| `unique_arbitrary_values_default` | **5.59** | 5.67 (1.01×) | 886 (158×) |
| `unique_tokens_default` | 18.6 (1.02×) | **18.4** | 1,088 (59.3×) |
| `repeated_default` | **0.26** | 0.27 (1.03×) | 813 (3,120×) |
| `recurring_128` | **0.29** | 0.29 (1.03×) | 848 (2,966×) |
| `recurring_1024` | 2.73 (1.03×) | **2.66** | 855 (321×) |
| `nested_array_inputs` | **0.37** | 0.41 (1.09×) | 818 (2,204×) |
| `external_cache_hits` | **0.27** | 0.27 (1.01×) | 0.48 (1.82×) |

| Cold default merger, bytes | php-cn | tales-from-a-dev | gehrisandro |
| --- | ---: | ---: | ---: |
| Peak PHP-used memory | 778,272 | 1,238,176 | 1,384,824 |
| PHP-used memory after merge | 620,240 | 1,234,624 | 861,096 |

## OPcache off

| Workload | php-cn | tales-from-a-dev | gehrisandro |
| --- | ---: | ---: | ---: |
| `cold_first_merge` | **1,387** | 2,285 (1.65×) | 2,207 (1.59×) |
| `unique_strings` | 3.93 (1.22×) | **3.22** | 916 (284×) |
| `unique_arbitrary_values` | 6.31 (1.13×) | **5.60** | 957 (171×) |
| `realistic_mix` | 1.59 (1.13×) | **1.40** | 599 (428×) |
| `unique_strings_default` | 3.96 (1.12×) | **3.54** | 918 (259×) |
| `unique_arbitrary_values_default` | 6.09 (1.02×) | **5.94** | 939 (158×) |
| `unique_tokens_default` | 21.1 (1.06×) | **19.8** | 1,158 (58.4×) |
| `repeated_default` | **0.28** | 0.30 (1.07×) | 877 (3,138×) |
| `recurring_128` | **0.30** | 0.32 (1.07×) | 932 (3,099×) |
| `recurring_1024` | **2.73** | 2.76 (1.01×) | 911 (333×) |
| `nested_array_inputs` | **0.39** | 0.45 (1.16×) | 874 (2,263×) |
| `external_cache_hits` | **0.28** | 0.30 (1.05×) | 0.50 (1.79×) |

| Cold default merger, bytes | php-cn | tales-from-a-dev | gehrisandro |
| --- | ---: | ---: | ---: |
| Peak PHP-used memory | 1,851,040 | 2,776,280 | 1,916,024 |
| PHP-used memory after merge | 1,366,264 | 2,295,248 | 1,381,160 |

## Workloads

| Workload | What it does |
| --- | --- |
| `cold_first_merge` | Creates the merger and runs one merge in a fresh process |
| `unique_strings`, `unique_arbitrary_values`, `realistic_mix` | New inputs with `cacheSize => 0`, so no result cache |
| `*_default` | New inputs with default settings. In `unique_tokens_default`, every arbitrary value is new too |
| `repeated_default`, `recurring_128`, `recurring_1024` | The same input, or a cycle of 128 or 1,024 inputs |
| `nested_array_inputs` | Nested arrays of classes |
| `external_cache_hits` | Hits on a warm in-memory PSR-16 cache |

gehrisandro has no in-memory cache, so its rows are uncached merges. The Laravel package always adds Laravel's cache store, which isn't measured here.

## Reproduce

Install each library in its own directory, for example:

```sh
composer require --working-dir=/tmp/tales tales-from-a-dev/tailwind-merge-php
composer require --working-dir=/tmp/gehri gehrisandro/tailwind-merge-laravel
```

Then, from this directory, run each library in each OPcache mode and compare:

```sh
XDEBUG_MODE=off php run.php --library=php-cn --opcache=on --output=results/php-cn-opcache-on.json
XDEBUG_MODE=off php run.php --library=tales-from-a-dev --root=/tmp/tales --opcache=on --output=results/tales-from-a-dev-opcache-on.json
XDEBUG_MODE=off php run.php --library=gehrisandro --root=/tmp/gehri --opcache=on --output=results/gehrisandro-opcache-on.json
php compare.php results on
```

Each library loads only its own source and `psr/simple-cache`, so Composer and Laravel don't affect the startup or memory figures.
