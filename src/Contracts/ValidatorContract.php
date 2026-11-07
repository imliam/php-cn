<?php

declare(strict_types=1);

namespace Cn\Contracts;

/**
 * @internal
 */
interface ValidatorContract
{
    public static function validate(string $value): bool;
}
