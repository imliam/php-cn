<?php

declare(strict_types=1);

namespace Cn\Validators;

use Cn\Contracts\ValidatorContract;
use Cn\Support\Str;
use Cn\Validators\Concerns\ValidatesArbitraryValue;

/**
 * @internal
 */
class ArbitraryShadowValidator implements ValidatorContract
{
    use ValidatesArbitraryValue;

    final public const SHADOW_REGEX = '/^(inset_)?-?((\d+)?\.?(\d+)[a-z]+|0)_-?((\d+)?\.?(\d+)[a-z]+|0)/';

    public static function validate(string $value): bool
    {
        return self::getIsArbitraryValue($value, 'shadow', self::isShadow(...));
    }

    private static function isShadow(string $value): bool
    {
        return Str::hasMatch(self::SHADOW_REGEX, $value);
    }
}
