<?php

declare(strict_types=1);

namespace Cn\Validators;

use Cn\Contracts\ValidatorContract;
use Cn\Support\Str;
use Cn\Validators\Concerns\ValidatesArbitraryValue;

/**
 * @internal
 */
class ArbitraryLengthValidator implements ValidatorContract
{
    use ValidatesArbitraryValue;

    final public const LENGTH_UNIT_REGEX = '/\d+(%|px|r?em|[sdl]?v([hwib]|min|max)|pt|pc|in|cm|mm|cap|ch|ex|r?lh|cq(w|h|i|b|min|max))|\b(calc|min|max|clamp)\(.+\)|^0$/';

    public static function validate(string $value): bool
    {
        return self::getIsArbitraryValue($value, 'length', self::isLengthOnly(...));
    }

    private static function isLengthOnly(string $value): bool
    {
        return Str::hasMatch(self::LENGTH_UNIT_REGEX, $value)
            && preg_match('/^(rgba?|hsla?|hwb|(ok)?(lab|lch)|color-mix)\(.+\)$/D', $value) !== 1;
    }
}
