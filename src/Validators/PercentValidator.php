<?php

declare(strict_types=1);

namespace Cn\Validators;

use Cn\Contracts\ValidatorContract;

/**
 * @internal
 */
class PercentValidator implements ValidatorContract
{
    public static function validate(string $value): bool
    {
        return str_ends_with($value, '%') && NumberValidator::validate(substr($value, 0, -1));
    }
}
