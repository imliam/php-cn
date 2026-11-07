<?php

declare(strict_types=1);

namespace Cn\Validators;

use Cn\Contracts\ValidatorContract;

/**
 * @internal
 */
class AnyNonArbitraryValidator implements ValidatorContract
{
    public static function validate(string $value): bool
    {
        return ! ArbitraryValueValidator::validate($value) && ! ArbitraryVariableValidator::validate($value);
    }
}
