<?php

namespace Cn\Support;

use Cn\Contracts\ValidatorContract;

/**
 * @internal
 * @phpstan-import-type Configuration from Config
 * @phpstan-type Tables array{configuration: Configuration, groups: list<string>, classes: array<array-key, int>, validators: array<array-key, list<array{class-string<ValidatorContract>, int}>>, maxValidatorPrefixLength: int, sourceHash: string}
 */
final class CompiledClassMap
{
    /** @var Tables|null */
    private static ?array $tables = null;

    /** @var Tables */
    private readonly array $data;

    public function __construct()
    {
        $this->data = self::$tables ??= CompiledConfigData::get();
    }

    /** @return Configuration */
    public function configuration(): array
    {
        return $this->data['configuration'];
    }

    public function sourceHash(): string
    {
        return $this->data['sourceHash'];
    }

    public function getClassGroupId(string $class): ?string
    {
        $group = $this->data['classes'][$class] ?? null;
        if ($group !== null) {
            return $this->data['groups'][$group];
        }

        // Exact children take precedence, then validators from the deepest ancestor.
        // Hyphens in a long arbitrary value cannot introduce additional utility prefixes.
        $prefix = substr($class, 0, $this->data['maxValidatorPrefixLength'] + 1);
        while (($position = strrpos($prefix, '-')) !== false) {
            $prefix = substr($prefix, 0, $position);
            if ($prefix === '') {
                break;
            }

            if (! isset($this->data['validators'][$prefix])) {
                continue;
            }

            $rest = substr($class, $position + 1);
            foreach ($this->data['validators'][$prefix] as [$validator, $group]) {
                if ($validator::validate($rest)) {
                    return $this->data['groups'][$group];
                }
            }
        }

        foreach ($this->data['validators'][''] ?? [] as [$validator, $group]) {
            if ($validator::validate($class)) {
                return $this->data['groups'][$group];
            }
        }

        return null;
    }
}
