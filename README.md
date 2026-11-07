# php-cn

Join conditional CSS classes and resolve Tailwind CSS conflicts in PHP.

```php
$isActive = true;

cn('px-2 py-1', ['bg-blue-500 text-white' => $isActive], 'px-4');
// 'py-1 bg-blue-500 text-white px-4'
```

A fork of [tailwind-merge-php](https://github.com/gehrisandro/tailwind-merge-php) by Sandro Gehri, inspired by the API and performance work in [shadcn-ui/cn](https://github.com/shadcn-ui/cn). The merge rules come from [tailwind-merge](https://github.com/dcastil/tailwind-merge).

Supports Tailwind CSS v4.0 through v4.3, using the complete default configuration from `tailwind-merge` 3.6.0. See [upgrading from tailwind-merge-php](#upgrading-from-tailwind-merge-php) for migration details.

## Table of contents

- [Get started](#get-started)
- [Usage](#usage)
- [Cache](#cache)
- [Configuration](#configuration)
- [Upgrading from tailwind-merge-php](#upgrading-from-tailwind-merge-php)
- [Benchmarks](#benchmarks)
- [Contributing](#contributing)

## Get started

> **Requires [PHP 8.2+](https://php.net/releases/)**

The Composer package name for this fork is `imliam/php-cn`. Until its first Packagist release, configure a Composer path or VCS repository pointing to your checkout before requiring it:

```bash
composer require imliam/php-cn:@dev
```

After loading Composer's autoloader, call `cn()` directly for conditional joining and Tailwind conflict resolution:

```php
cn('text-red-500', 'text-blue-500'); // 'text-blue-500'
```

The `Cn\TailwindMerge` class API is also available:

```php
use Cn\TailwindMerge;

$tw = TailwindMerge::instance();

$tw->merge('text-red-500', 'text-blue-500'); // 'text-blue-500'
```

You can adjust the configuration of `TailwindMerge` by using the factory to create a new instance:

```php
use Cn\TailwindMerge;

$instance = TailwindMerge::factory()
    ->withConfiguration([
        'prefix' => 'tw',
    ])->make();

$instance->merge('tw:text-red-500', 'tw:text-blue-500'); // 'tw:text-blue-500'
```

For more information on how to configure `TailwindMerge`, see the [Configuration](#configuration) section.

## Usage

`cn()` and `clsx()` are global functions, available automatically through Composer. If either name is already defined when this package loads, that function is preserved. You can always call this package explicitly through `\Cn\cn()` and `\Cn\clsx()`.

`cn()` accepts strings, nested arrays, and conditional maps. String keys are class names; their values decide whether to include them using PHP truthiness. Integer keys contain ordinary class inputs.

```php
$isActive = true;
$isDisabled = false;

cn(
    'rounded px-2',
    ['bg-blue-500 text-white' => $isActive, 'opacity-50' => $isDisabled],
    ['py-1', [$isDisabled ? 'cursor-not-allowed' : null]],
    'px-4',
); // 'rounded bg-blue-500 text-white py-1 px-4'

// Join any CSS classes without resolving Tailwind conflicts.
clsx('px-2', ['active' => $isActive], 'px-4'); // 'px-2 active px-4'
```

Use a ternary or conditional map for conditions. PHP's `$isActive && 'bg-blue-500'` produces a boolean, so it cannot pass the class string to either helper.

Both helpers ignore standalone booleans, `null`, empty strings, and numeric zero. Nonzero numbers and `Stringable` objects become strings. The string `'0'` is retained as a class input, but is false when used as a map condition. Numeric array keys represent inputs, so put numeric class names in strings rather than map keys.

`cn()` uses the shared default merger and its caches. For a custom configuration, pass `clsx(...)` to your configured merger's `merge()` method. Existing calls to `merge()` still flatten array values; conditional maps belong to the new helpers.

Both `cn()` and `TailwindMerge::merge()` handle variants, arbitrary values, and other Tailwind syntax:

```php
use Cn\TailwindMerge;

$tw = TailwindMerge::instance();

// conflicting classes
$tw->merge('block inline'); // inline
$tw->merge('pl-4 px-6'); // px-6

// non-conflicting classes
$tw->merge('text-xl text-black'); // text-xl text-black

// with breakpoints
$tw->merge('h-10 lg:h-12 lg:h-20'); // h-10 lg:h-20

// dark mode
$tw->merge('text-black dark:text-white dark:text-gray-700'); // text-black dark:text-gray-700

// with hover, focus and other states
$tw->merge('hover:block hover:inline'); // hover:inline

// with the important modifier
$tw->merge('font-medium! font-bold!'); // font-bold!

// CSS variable shorthand
$tw->merge('w-4 w-(--sidebar-width)'); // w-(--sidebar-width)

// arbitrary values
$tw->merge('z-10 z-[999]'); // z-[999] 

// arbitrary variants
$tw->merge('[&>*]:underline [&>*]:line-through'); // [&>*]:line-through

// non tailwind classes
$tw->merge('non-tailwind-class block inline'); // non-tailwind-class inline
```

It's possible to pass the classes as a string, an array or a combination of both:

```php
$tw->merge('h-10 h-20'); // h-20
$tw->merge(['h-10', 'h-20']); // h-20
$tw->merge(['h-10', 'h-20'], 'h-30'); // h-30
$tw->merge(['h-10', 'h-20'], 'h-30', ['h-40']); // h-40
```

## Cache

The default merger uses compiled lookup tables shipped with the package. No application build step is required. Each merger caches complete results and parsed classes in memory. `TailwindMerge::instance()` returns a shared default merger. Use the factory for an independent instance with its own configuration and caches.

The `cacheSize` option defaults to 500 entries per generation. Each of the two caches keeps at most two generations, so memory use stays bounded in long-running workers. Set `cacheSize` to `0` to disable both caches:

```php
$tw = TailwindMerge::factory()->withConfiguration(['cacheSize' => 0])->make();
```

For an external result cache, pass a cache instance to `withCache`.

It accepts any [PSR-16](https://www.php-fig.org/psr/psr-16/) compatible cache implementation.

```php
TailwindMerge::factory()
  ->withCache($cache)
  ->make();
```

External cache keys include the configuration. Equivalent configurations can share results across instances and requests. Configurations containing anonymous or bound validator closures use a separate namespace per merger, since their behavior cannot be safely serialized. External caching replaces the in-memory result cache; the parsed-class cache still uses `cacheSize`. Validators should be deterministic for the lifetime of a merger.

## Configuration

The default configuration works with standard Tailwind CSS. Customize it when you add utilities the default rules cannot recognize.

If you're using a custom Tailwind config, you may need to configure TailwindMerge as well to merge classes properly.

By default TailwindMerge is configured in a way that you can still use it if all the following apply to your Tailwind config:

- Only using color names which don't clash with other Tailwind class names
- Only deviating by number values from number-based Tailwind classes
- Only using font-family classes which don't clash with default font-weight classes
- Sticking to default Tailwind config for everything else

If some of these points don't apply to you, you need to customize the configuration.

This is an example to add a custom font size of "very-large":
```php
TailwindMerge::factory()->withConfiguration([
        'classGroups' => [
            'font-size' => [
                ['text' => ['very-large']]
            ],
        ],
    ])->make();
```

`withConfiguration()` extends list values and recursively merges maps; scalar values replace their defaults. Theme keys follow Tailwind 4 namespaces, such as `color`, `font`, `text`, `font-weight`, `spacing`, `radius`, and `shadow`:

```php
$tw = TailwindMerge::factory()->withConfiguration([
    'theme' => ['text' => ['hero']],
])->make();

$tw->merge('text-sm text-hero'); // text-hero
```

Changing only `cacheSize`, the modifier `separator`, or a Tailwind 4 `prefix` keeps the compiled lookup tables. Custom themes, class groups, conflict rules, modifier settings, and legacy hyphen prefixes use the runtime class-map builder. Both paths retain the same merge semantics. See the [compiled table benchmarks](benchmarks/compiled.md) for their startup, memory, and merge costs.

For the class-group and conflict model, see the [upstream configuration documentation](https://github.com/dcastil/tailwind-merge/blob/v3.6.0/docs/configuration.md). This PHP API accepts the configuration directly, without the JavaScript `extend`/`override` wrappers. Custom utilities that the default configuration cannot recognize need explicit class groups. Arbitrary properties conflict with the same property, but are not matched against equivalent standard utilities.

## Upgrading from tailwind-merge-php

All package classes and interfaces now use the `Cn\` namespace. Update imports from `TailwindMerge\` to `Cn\`, including any contracts, validators, and configuration classes. Class names and factory methods stay the same. There are no aliases for the old namespace.

```diff
-use TailwindMerge\TailwindMerge;
+use Cn\TailwindMerge;
```

Adopting the global `cn()` helper is optional. The default rules target Tailwind 4. Applications using Tailwind 3 should stay on their existing package until they upgrade Tailwind.

Replace the Composer dependency with `imliam/php-cn`. For this unreleased checkout, first configure a path or VCS repository as described above.

```bash
composer remove gehrisandro/tailwind-merge-php --no-update
composer require imliam/php-cn:@dev
```

The packages use separate namespaces and can coexist during migration. This fork does not declare itself a Composer replacement for upstream. Integrations that require the original package, including Laravel wrappers, need their imports and dependency constraints updated before migration.

Review these behavior changes when moving from the Tailwind 3 implementation:

- Use Tailwind 4 utility names, such as `bg-linear-to-r`. Removed Tailwind 3 utilities are no longer part of the default configuration.
- Use `prefix => 'tw'` for `tw:hover:p-4`. A prefix ending in `-` retains the legacy class-prefix syntax for existing integrations.
- Put `!` at the end of a utility. Leading `!` remains supported, including slash line-height conflicts.
- Update custom theme keys to Tailwind 4 namespaces. For example, extend `spacing` rather than the old `margin` scale, and `color` rather than `colors`.
- Unknown classes, including duplicates, now pass through unchanged. Order-sensitive variants such as `before`, `after`, `*`, and `**` retain their order.
- Factory configuration is isolated to the instance. Creating another merger no longer changes existing instances.
- Theme references returned by `Config::fromTheme()` are immutable. Create a new reference to select a different theme key.

Two edge cases deliberately correct upstream behavior: slash line heights work with the legacy leading `!` marker, and custom group IDs cannot collide with a concatenation of another group ID and its variants.

The [benchmark report](benchmarks/README.md) contains reproducible before/after measurements and the optimizations informed by `cn`.

## Benchmarks

Measured on PHP 8.5.8, macOS ARM64, using medians of seven isolated processes with identical autoloaders. These results measure the `TailwindMerge::merge()` API at the recorded revisions, before the `cn()` helper was added.

The [original Tailwind 3 to 4 comparison](benchmarks/README.md) measured **39–46× faster uncached merges**, from 939–969 µs to 21–25 µs, with OPcache disabled. That comparison used the intermediate Tailwind 4 engine, before compiled defaults.

The [compiled-table experiment](benchmarks/compiled.md) then compared that Tailwind 4 engine with generated default lookup tables:

| Workload | OPcache | Runtime configuration | Compiled defaults |
| --- | --- | ---: | ---: |
| First merger and merge | Off | 2,255 µs | 1,259 µs |
| First merger and merge | Prewarmed | 654 µs | 125 µs |
| Uncached component merge | Off | 20.57 µs | 22.12 µs |
| Uncached component merge | Prewarmed | 19.98 µs | 20.11 µs |
| Repeated cached merge | Off | 0.286 µs | 0.288 µs |
| Repeated cached merge | Prewarmed | 0.272 µs | 0.266 µs |

Compiled defaults reduce initialization time by **1.79× without OPcache** and **5.22× with prewarmed bytecode**. Cold peak PHP-used memory falls **13%** and **49.5%**, respectively, while cached library scripts use about **200 KiB more shared OPcache memory**. Custom configurations retain the runtime path.

The ordinary uncached, arbitrary-value, and recurring workloads take 5–8% longer without OPcache. Warm workloads with OPcache remain within about 2% of the baseline. Prewarming compiles source files outside the timer without initializing a merger; these first-use figures exclude PHP process startup and do not measure complete PHP-FPM request latency.

Both reports include raw samples, source hashes, methodology, and reproduction commands. The compiled comparison also preserves its baseline source archive.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for setup and checks, and the [upstream maintenance guide](resources/upstream/README.md) for updating Tailwind support and regenerating the compiled tables.

---

`php-cn` is licensed under the [MIT license](LICENSE.md). It retains the original project's copyright and attribution.
