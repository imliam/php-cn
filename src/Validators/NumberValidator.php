<?php

declare(strict_types=1);

namespace Cn\Validators;

use Cn\Contracts\ValidatorContract;

/**
 * @internal
 */
class NumberValidator implements ValidatorContract
{
    public static function validate(string $value): bool
    {
        if (is_numeric($value)) {
            return true;
        }

        // Match JavaScript Number(), including base-prefixed numbers and Infinity.
        $trimmed = trim($value);

        return ($value !== '' && $trimmed === '')
            || in_array($trimmed, ['Infinity', '+Infinity', '-Infinity'], true)
            || preg_match('/^0(?:x[0-9a-f]+|b[01]+|o[0-7]+)$/Di', $trimmed) === 1;
    }
}
