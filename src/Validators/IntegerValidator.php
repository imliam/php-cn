<?php

declare(strict_types=1);

namespace Cn\Validators;

use Cn\Contracts\ValidatorContract;

/**
 * @internal
 */
class IntegerValidator implements ValidatorContract
{
    public static function validate(string $value): bool
    {
        if (! NumberValidator::validate($value) || str_contains($value, 'Infinity')) {
            return false;
        }

        $number = is_numeric($value) ? (float) $value : (float) intval($value, 0);

        return is_finite($number) && floor($number) === $number;
    }
}
