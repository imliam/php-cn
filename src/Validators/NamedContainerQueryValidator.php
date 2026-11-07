<?php

declare(strict_types=1);

namespace Cn\Validators;

use Cn\Contracts\ValidatorContract;

/**
 * @internal
 */
class NamedContainerQueryValidator implements ValidatorContract
{
    public static function validate(string $value): bool
    {
        return preg_match('~^@container(?:-size|-normal)?/.+~', $value) === 1;
    }
}
