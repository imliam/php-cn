<?php

namespace Cn\Validators\Concerns;

use Cn\Validators\ArbitraryValueValidator;

/**
 * @internal
 */
trait ValidatesArbitraryValue
{
    /**
     * @param  string|array<array-key, string>  $labels
     * @param  callable(string): bool  $isLengthOnly
     */
    protected static function getIsArbitraryValue(string $value, string|array $labels, callable $isLengthOnly): bool
    {
        $labels = is_string($labels) ? [$labels] : $labels;

        preg_match(ArbitraryValueValidator::ARBITRARY_VALUE_REGEX, $value, $result);

        if ($result !== []) {
            if ($result[1] !== '') {
                return in_array($result[1], $labels, true);
            }

            return $isLengthOnly($result[2]);
        }

        return false;
    }
}
