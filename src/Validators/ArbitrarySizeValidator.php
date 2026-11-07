<?php

declare(strict_types=1);

namespace Cn\Validators;

use Cn\Contracts\ValidatorContract;
use Cn\Validators\Concerns\ValidatesArbitraryValue;

/**
 * @internal
 */
class ArbitrarySizeValidator implements ValidatorContract
{
    use ValidatesArbitraryValue;

    public static function validate(string $value): bool
    {
        return self::getIsArbitraryValue($value, ['length', 'size', 'bg-size'], fn (): bool => false);
    }
}
