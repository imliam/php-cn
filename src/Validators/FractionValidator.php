<?php

declare(strict_types=1);

namespace Cn\Validators;

use Cn\Contracts\ValidatorContract;

/**
 * @internal
 */
class FractionValidator implements ValidatorContract
{
    public static function validate(string $value): bool
    {
        return preg_match('/^\d+(?:\.\d+)?\/\d+(?:\.\d+)?$/D', $value) === 1;
    }
}
