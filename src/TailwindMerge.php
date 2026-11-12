<?php

namespace Cn;

use Psr\SimpleCache\CacheInterface;
use Cn\Contracts\TailwindMergeContract;
use Cn\Support\BoundedCache;
use Cn\Support\CompiledClassMap;
use Cn\Support\Config;
use Cn\Support\TailwindClassParser;
use Cn\ValueObjects\ParsedClass;

/**
 * @phpstan-import-type Configuration from Config
 */
class TailwindMerge implements TailwindMergeContract
{
    // Distinct class names are far fewer than distinct inputs, so this memo can hold many more entries.
    private const CLASS_CACHE_SIZE = 5000;

    private static ?self $instance = null;

    private ?TailwindClassParser $parser = null;

    /** @var BoundedCache<string> */
    private readonly BoundedCache $results;

    /**
     * Class name => null when always kept, otherwise its class ID and the IDs it conflicts with.
     *
     * @var array<string, array{string, list<string>}|null>
     */
    private array $classes = [];

    /** @var array<string, array{string, list<string>}|null> */
    private array $previousClasses = [];

    /**
     * Classes with the same modifiers and group share one entry instead of copying its strings.
     *
     * @var array<string, array{string, list<string>}>
     */
    private array $resolvedGroups = [];

    private readonly int $classCacheSize;

    /** @var array<array-key, list<string>> */
    private readonly array $conflictingGroups;

    /** @var array<array-key, list<string>> */
    private readonly array $postfixConflictingGroups;

    private ?string $cacheNamespace = null;

    public static function instance(): self
    {
        return self::$instance ??= self::factory()->make();
    }

    public static function factory(): Factory
    {
        return new Factory();
    }

    /**
     * @param  Configuration  $configuration
     */
    public function __construct(
        private readonly array $configuration,
        private readonly ?CacheInterface $cache = null,
        private readonly ?CompiledClassMap $compiledClassMap = null,
    ) {
        $cacheSize = $configuration['cacheSize'];
        $cacheSize = max(0, $cacheSize);
        $this->results = new BoundedCache($cacheSize);
        $this->classCacheSize = max($cacheSize, self::CLASS_CACHE_SIZE);
        $this->conflictingGroups = $configuration['conflictingClassGroups'];
        $this->postfixConflictingGroups = $configuration['conflictingClassGroupModifiers'];
    }

    /**
     * @param  string|array<array-key, mixed>  ...$args
     */
    public function merge(...$args): string
    {
        if (count($args) === 1 && is_string($args[0])) {
            $input = $args[0];
        } else {
            $parts = [];
            self::flatten($args, $parts);
            $input = implode(' ', $parts);
        }

        $cached = $this->results->get($input);
        if ($cached !== null) {
            return $cached;
        }

        if ($this->cache instanceof CacheInterface) {
            $key = hash('xxh3', $this->getCacheNamespace() . $input);
            $result = $this->cache->get($key);
            if (! is_string($result)) {
                $result = $this->mergeClassList($input);
                $this->cache->set($key, $result);
            }
        } else {
            $result = $this->mergeClassList($input);
        }

        $this->results->set($input, $result);

        return $result;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @param  list<string>  $parts
     */
    private static function flatten(array $values, array &$parts): void
    {
        foreach ($values as $value) {
            if (is_array($value)) {
                self::flatten($value, $parts);
            } elseif (is_scalar($value) || $value instanceof \Stringable) {
                $parts[] = (string) $value;
            }
        }
    }

    private function mergeClassList(string $input): string
    {
        $classNames = preg_split('/\s+/', trim($input), -1, PREG_SPLIT_NO_EMPTY);
        if (! $classNames) {
            return '';
        }

        $parser = $this->parser ??= new TailwindClassParser($this->configuration, $this->compiledClassMap);
        $conflicts = [];
        $result = [];

        for ($index = count($classNames) - 1; $index >= 0; $index--) {
            $className = $classNames[$index];
            if (array_key_exists($className, $this->classes)) {
                $resolved = $this->classes[$className];
            } else {
                $resolved = array_key_exists($className, $this->previousClasses)
                    ? $this->previousClasses[$className]
                    : $this->resolve($parser->parse($className));
                $this->classes[$className] = $resolved;
                if (count($this->classes) >= $this->classCacheSize) {
                    $this->previousClasses = $this->classes;
                    $this->classes = [];
                    $this->resolvedGroups = [];
                }
            }

            if ($resolved === null) {
                if (! isset($seen[$className])) {
                    $seen[$className] = true;
                    $result[] = $className;
                }

                continue;
            }

            if (isset($conflicts[$resolved[0]])) {
                continue;
            }

            $conflicts[$resolved[0]] = true;
            foreach ($resolved[1] as $conflictingId) {
                $conflicts[$conflictingId] = true;
            }

            $result[] = $className;
        }

        return implode(' ', array_reverse($result));
    }

    /**
     * @return array{string, list<string>}|null
     */
    private function resolve(ParsedClass $class): ?array
    {
        $group = $class->classGroupId;
        if ($group === null) {
            return null;
        }

        // Separate modifier and group IDs so custom names cannot collide.
        $modifier = $class->modifierId . "\0";
        $classId = $modifier . $group;
        $key = $class->hasPostfixModifier ? $classId . "\0/" : $classId;
        if (isset($this->resolvedGroups[$key])) {
            return $this->resolvedGroups[$key];
        }

        $conflicting = [];
        foreach ($this->conflictingGroups[$group] ?? [] as $conflictingGroup) {
            $conflicting[] = $modifier . $conflictingGroup;
        }
        if ($class->hasPostfixModifier) {
            foreach ($this->postfixConflictingGroups[$group] ?? [] as $conflictingGroup) {
                $conflicting[] = $modifier . $conflictingGroup;
            }
        }

        return $this->resolvedGroups[$key] = [$classId, $conflicting];
    }

    private function getCacheNamespace(): string
    {
        if ($this->cacheNamespace !== null) {
            return $this->cacheNamespace;
        }

        if ($this->compiledClassMap instanceof CompiledClassMap) {
            return $this->cacheNamespace = 'tailwind-merge-php-compiled-v1:' . $this->compiledClassMap->sourceHash() . ':' . hash('xxh3', serialize($this->configuration)) . ':';
        }

        // Default configuration is immutable and can share results between requests.
        if ($this->configuration === Config::getDefaultConfig()) {
            return $this->cacheNamespace = 'tailwind-merge-php-v4-1:';
        }

        try {
            $configuration = serialize(self::cacheableConfiguration($this->configuration));
        } catch (\Exception) {
            // Closures cannot be serialized. Keep their results local to this merger.
            $configuration = random_bytes(32);
        }

        return $this->cacheNamespace = 'tailwind-merge-php-v4-1:' . hash('xxh3', $configuration) . ':';
    }

    private static function cacheableConfiguration(mixed $value): mixed
    {
        if (is_array($value)) {
            return ['array' => array_map(self::cacheableConfiguration(...), $value)];
        }

        if ($value instanceof \Closure) {
            $function = new \ReflectionFunction($value);
            if ($function->getClosureThis() !== null || str_contains($function->getName(), '{closure')) {
                throw new \RuntimeException('Anonymous or bound validators need an instance-local cache namespace.');
            }

            return ['callable' => [$function->getClosureCalledClass()?->getName(), $function->getName()]];
        }

        return $value;
    }
}
