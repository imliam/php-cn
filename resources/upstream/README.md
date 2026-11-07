The default configuration and validator behavior are ported from
[tailwind-merge 3.6.0](https://github.com/dcastil/tailwind-merge/tree/bceabfd95eab05553d15c5368b2684de697a84eb),
commit `bceabfd95eab05553d15c5368b2684de697a84eb`, which supports Tailwind CSS 4.0–4.3.
The upstream MIT license is included in [tailwind-merge-LICENSE.md](tailwind-merge-LICENSE.md).

`src/Support/default-config.php` contains all 379 upstream class groups, theme
namespaces, directional conflicts, postfix lookup groups, and order-sensitive
modifiers. It is generated as PHP arrays and callables; Node.js is only needed to
regenerate development artifacts, never to run the library.

`src/Support/CompiledConfigData.php` is the compiled lookup data used for the
default configuration. The PHP compiler builds the normal class trie once, then
exports exact class names and ordered validator lists with integer group IDs.
It records the longest validator prefix in bytes, which bounds prefix searches
even when arbitrary values contain long URLs with many hyphens.
The generated file also contains conflict rules and parser settings, so the
default merger can load it without constructing theme getters, validator
closures, or trie nodes. Custom class groups and themes still use the runtime
configuration builder.

To reproduce the configuration and differential fixtures, with Node.js 22.13 or newer:

```sh
git clone https://github.com/dcastil/tailwind-merge.git /tmp/tailwind-merge
git -C /tmp/tailwind-merge checkout bceabfd95eab05553d15c5368b2684de697a84eb
node scripts/generate-tailwind-config.mjs /tmp/tailwind-merge
php scripts/compile-default-config.php
node scripts/generate-upstream-fixtures.mjs /tmp/tailwind-merge
vendor/bin/pest tests/Feature/UpstreamTailwind4Test.php
```

The fixture generator executes the pinned upstream implementation to obtain
expected results. It covers every class group using literal utilities, validator
samples, directional conflicts, variants, and important modifiers, plus upstream's
hand-written validator tests. The checked-in fixtures run with PHP alone.

When upgrading upstream, change the pinned revision and documented version in
both scripts, review upstream validator changes, regenerate the artifacts, and
run the full PHP test suite. Custom Tailwind utility namespaces still require
custom configuration, as they do in upstream tailwind-merge.

Regenerate the compiled data after changing the default configuration or the
class-map builder. Check that the committed artifact matches the current source
with:

```sh
php scripts/compile-default-config.php --check
```

Compilation requires only PHP and the Composer autoloader. It has no Node.js or
upstream checkout dependency. The compiler accepts an optional output path for
comparison and records the default configuration's SHA-256 hash in the artifact.
It fails on validators that cannot be represented as named public static
`ValidatorContract::validate` methods. New class groups and existing validators
need only regeneration; new validator behavior still requires a PHP validator.
Review generated diffs and run `composer test` before releasing an update.

`composer build:config` regenerates the compiled tables. `composer test` includes
`composer test:compiled`, which rejects a missing or stale generated artifact.
Application users receive the generated file in the package and do not need to
run either generator during installation or deployment.

The compiled result-cache namespace includes the default configuration hash.
When changing parser or validator behavior without changing that configuration,
bump the corresponding cache namespace versions in `TailwindMerge.php` so
external PSR caches cannot return results from the previous semantics.
