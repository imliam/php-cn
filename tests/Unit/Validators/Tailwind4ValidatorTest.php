<?php

use Cn\Validators\ArbitraryLengthValidator;
use Cn\Validators\ArbitraryPositionValidator;
use Cn\Validators\ArbitraryShadowValidator;
use Cn\Validators\ArbitrarySizeValidator;
use Cn\Validators\FractionValidator;
use Cn\Validators\IntegerValidator;

test('color functions with percentages are not arbitrary lengths', function (string $value) {
    expect(ArbitraryLengthValidator::validate($value))->toBeFalse();
})->with([
    '[hsl(0_0%_0%)]',
    '[rgb(0_0_0/50%)]',
    '[oklch(50%_0.2_100)]',
    '[color-mix(in_oklab,red_50%,blue)]',
]);

test('Tailwind 4 data type hints distinguish position size and shadow', function () {
    expect(ArbitraryPositionValidator::validate('[percentage:50%]'))->toBeTrue()
        ->and(ArbitrarySizeValidator::validate('[percentage:50%]'))->toBeFalse()
        ->and(ArbitrarySizeValidator::validate('[bg-size:auto_100%]'))->toBeTrue()
        ->and(ArbitraryShadowValidator::validate('[shadow:var(--custom)]'))->toBeTrue();
});

test('dynamic numeric scales accept decimal fractions and integral numeric strings', function () {
    expect(FractionValidator::validate('2.5/3'))->toBeTrue()
        ->and(FractionValidator::validate('1/2/3'))->toBeFalse()
        ->and(IntegerValidator::validate('2.0'))->toBeTrue()
        ->and(IntegerValidator::validate('1e3'))->toBeTrue()
        ->and(IntegerValidator::validate('Infinity'))->toBeFalse();
});
