<?php

declare(strict_types=1);

namespace Cn\Validators;

use Cn\Contracts\ValidatorContract;
use Cn\Support\Str;

/**
 * @internal
 */
class ArbitraryValueValidator implements ValidatorContract
{
    final public const ARBITRARY_VALUE_REGEX = '/^\[(?:(\w[\w-]*):)?(.+)\]$/Di';

    public static function validate(string $value): bool
    {
        return ($value[0] ?? '') === '[' && Str::hasMatch(self::ARBITRARY_VALUE_REGEX, $value);
    }
}
