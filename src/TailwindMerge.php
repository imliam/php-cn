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
    private static ?self $instance = null;

    private ?TailwindClassParser $parser = null;

    /** @var BoundedCache<string> */
    private readonly BoundedCache $results;

    /** @var BoundedCache<ParsedClass> */
    private readonly BoundedCache $classes;

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
        $this->classes = new BoundedCache($cacheSize);
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

        if ($this->cache instanceof CacheInterface) {
            $key = hash('xxh3', $this->getCacheNamespace() . $input);
            $cached = $this->cache->get($key);
            if (is_string($cached)) {
                return $cached;
            }

            $result = $this->mergeClassList($input);
            $this->cache->set($key, $result);

            return $result;
        }

        $cached = $this->results->get($input);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->mergeClassList($input);
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
            $class = $this->classes->get($className);
            if ($class === null) {
                $class = $parser->parse($className);
                $this->classes->set($className, $class);
            }

            $group = $class->classGroupId;
            if ($group === null) {
                $result[] = $className;

                continue;
            }

            // Separate modifier and group IDs so custom names cannot collide.
            $modifier = $class->modifierId . "\0";
            $classId = $modifier . $group;
            if (isset($conflicts[$classId])) {
                continue;
            }

            $conflicts[$classId] = true;
            foreach ($this->conflictingGroups[$group] ?? [] as $conflictingGroup) {
                $conflicts[$modifier . $conflictingGroup] = true;
            }
            if ($class->hasPostfixModifier) {
                foreach ($this->postfixConflictingGroups[$group] ?? [] as $conflictingGroup) {
                    $conflicts[$modifier . $conflictingGroup] = true;
                }
            }

            $result[] = $className;
        }

        return implode(' ', array_reverse($result));
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
