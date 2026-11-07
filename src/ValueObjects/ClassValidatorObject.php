<?php

declare(strict_types=1);

namespace Cn\ValueObjects;

use Closure;

class ClassValidatorObject
{
    public function __construct(
        public string $classGroupId,
        public Closure $validator,
    ) {}
}
