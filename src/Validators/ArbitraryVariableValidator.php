<?php

declare(strict_types=1);

namespace Cn\Validators;

use Cn\Contracts\ValidatorContract;

/**
 * @internal
 */
class ArbitraryVariableValidator implements ValidatorContract
{
    final public const ARBITRARY_VARIABLE_REGEX = '/^\((?:(\w[\w-]*):)?(.+)\)$/Di';

    public static function validate(string $value): bool
    {
        return preg_match(self::ARBITRARY_VARIABLE_REGEX, $value) === 1;
    }
}
