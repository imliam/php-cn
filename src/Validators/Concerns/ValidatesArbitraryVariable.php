<?php

namespace Cn\Validators\Concerns;

use Cn\Validators\ArbitraryVariableValidator;

/**
 * @internal
 */
trait ValidatesArbitraryVariable
{
    /**
     * @param  list<string>  $labels
     */
    protected static function getIsArbitraryVariable(string $value, array $labels, bool $matchWithoutLabel = false): bool
    {
        if (($value[0] ?? '') !== '(' || preg_match(ArbitraryVariableValidator::ARBITRARY_VARIABLE_REGEX, $value, $matches) !== 1) {
            return false;
        }

        return $matches[1] !== '' ? in_array($matches[1], $labels, true) : $matchWithoutLabel;
    }
}
