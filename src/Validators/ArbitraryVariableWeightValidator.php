<?php

declare(strict_types=1);

namespace Cn\Validators;

use Cn\Contracts\ValidatorContract;
use Cn\Validators\Concerns\ValidatesArbitraryVariable;

/**
 * @internal
 */
class ArbitraryVariableWeightValidator implements ValidatorContract
{
    use ValidatesArbitraryVariable;

    public static function validate(string $value): bool
    {
        return self::getIsArbitraryVariable($value, ['number', 'weight'], true);
    }
}
