<?php

declare(strict_types=1);

namespace Cn\Validators;

use Cn\Contracts\ValidatorContract;
use Cn\Validators\Concerns\ValidatesArbitraryValue;

/**
 * @internal
 */
class ArbitraryPositionValidator implements ValidatorContract
{
    use ValidatesArbitraryValue;

    public static function validate(string $value): bool
    {
        return self::getIsArbitraryValue($value, ['position', 'percentage'], fn (): bool => false);
    }
}
