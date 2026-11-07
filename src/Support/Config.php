<?php

declare(strict_types=1);

namespace Cn\Support;

use InvalidArgumentException;
use Cn\ValueObjects\ThemeGetter;

/**
 * @phpstan-type Configuration array{cacheSize: int, prefix: ?string, separator?: string, theme: array<array-key, mixed>, classGroups: array<array-key, mixed>, conflictingClassGroups: array<array-key, list<string>>, conflictingClassGroupModifiers: array<array-key, list<string>>, postfixLookupClassGroups: list<string>, orderSensitiveModifiers: list<string>}
 */
class Config
{
    /**
     * @var array<string, mixed>
     */
    private static array $additionalConfig = [];

    /**
     * @param  array<string, mixed>|null  $additionalConfiguration
     * @return Configuration
     */
    public static function getMergedConfig(?array $additionalConfiguration = null): array
    {
        return self::merge(self::getDefaultConfig(), $additionalConfiguration ?? self::$additionalConfig);
    }

    /**
     * @internal
     * @param  Configuration  $config
     * @param  array<string, mixed>  $additionalConfiguration
     * @return Configuration
     */
    public static function merge(array $config, array $additionalConfiguration): array
    {
        if ($additionalConfiguration === []) {
            return $config;
        }

        foreach ($additionalConfiguration as $key => $additionalConfig) {
            $config[$key] = self::mergePropertyRecursively($config, $key, $additionalConfig);
        }

        self::validateConfiguration($config);

        return $config;
    }

    /**
     * @param  array<array-key, mixed>  $baseConfig
     */
    private static function mergePropertyRecursively(array $baseConfig, string|int $mergeKey, mixed $mergeValue): mixed
    {
        if (! array_key_exists($mergeKey, $baseConfig) || ! is_array($mergeValue) || ! is_array($baseConfig[$mergeKey])) {
            return $mergeValue;
        }

        if (array_is_list($mergeValue) && array_is_list($baseConfig[$mergeKey])) {
            return [...$baseConfig[$mergeKey], ...$mergeValue];
        }

        foreach ($mergeValue as $key => $value) {
            $baseConfig[$mergeKey][$key] = self::mergePropertyRecursively($baseConfig[$mergeKey], $key, $value);
        }

        return $baseConfig[$mergeKey];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function setAdditionalConfig(array $config): void
    {
        self::$additionalConfig = $config;
    }

    /**
     * @var Configuration|null
     */
    private static ?array $defaultConfig = null;

    /**
     * Tailwind CSS 4.0–4.3 configuration from tailwind-merge 3.6.0.
     *
     * @return Configuration
     */
    public static function getDefaultConfig(): array
    {
        if (self::$defaultConfig === null) {
            $config = require __DIR__ . '/default-config.php';
            self::validateConfiguration($config);
            self::$defaultConfig = $config;
        }

        return self::$defaultConfig;
    }

    /**
     * @phpstan-assert Configuration $config
     */
    private static function validateConfiguration(mixed $config): void
    {
        if (! is_array($config) || ! is_int($config['cacheSize'] ?? null)) {
            throw new InvalidArgumentException('The cacheSize configuration must be an integer.');
        }

        if (! array_key_exists('prefix', $config) || ($config['prefix'] !== null && ! is_string($config['prefix']))) {
            throw new InvalidArgumentException('The prefix configuration must be a string or null.');
        }

        if (array_key_exists('separator', $config) && (! is_string($config['separator']) || $config['separator'] === '')) {
            throw new InvalidArgumentException('The separator configuration must be a non-empty string.');
        }

        foreach (['theme', 'classGroups', 'conflictingClassGroups', 'conflictingClassGroupModifiers'] as $key) {
            if (! isset($config[$key]) || ! is_array($config[$key])) {
                throw new InvalidArgumentException("The $key configuration must be an array.");
            }

            foreach ($config[$key] as $values) {
                if (str_starts_with($key, 'conflicting')) {
                    self::validateStringList($values, $key);
                }
            }
        }

        foreach (['postfixLookupClassGroups', 'orderSensitiveModifiers'] as $key) {
            self::validateStringList($config[$key] ?? null, $key);
        }
    }

    private static function validateStringList(mixed $values, string $key): void
    {
        if (! is_array($values) || ! array_is_list($values)) {
            throw new InvalidArgumentException("The $key configuration must contain lists of strings.");
        }

        foreach ($values as $value) {
            if (! is_string($value)) {
                throw new InvalidArgumentException("The $key configuration must contain lists of strings.");
            }
        }
    }

    public static function fromTheme(string $key): ThemeGetter
    {
        return new ThemeGetter($key);
    }
}
