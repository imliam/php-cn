<?php

declare(strict_types=1);

namespace Cn\Validators;

use Cn\Contracts\ValidatorContract;
use Cn\Support\Str;

/**
 * @internal
 */
class TshirtSizeValidator implements ValidatorContract
{
    final public const T_SHIRT_UNIT_REGEX = '/^(\d+(\.\d+)?)?(xs|sm|md|lg|xl)$/';

    public static function validate(string $value): bool
    {
        return Str::hasMatch(self::T_SHIRT_UNIT_REGEX, $value);
    }
}
