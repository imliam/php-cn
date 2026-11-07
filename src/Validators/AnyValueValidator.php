<?php

declare(strict_types=1);

namespace Cn\Validators;

use Cn\Contracts\ValidatorContract;

/**
 * @internal
 */
class AnyValueValidator implements ValidatorContract
{
    public static function validate(string $value): bool
    {
        return true;
    }
}
