<?php

namespace Cn;

/**
 * Join class values and resolve conflicting Tailwind utilities.
 */
function cn(mixed ...$inputs): string
{
    return TailwindMerge::instance()->merge(clsx(...$inputs));
}

/**
 * Join strings, nonzero numbers, Stringable values, nested arrays, and conditional maps.
 * String-keyed array entries include their key when the value is truthy in PHP.
 * Standalone booleans, null, numeric zero, and empty strings are ignored; the string "0" is retained.
 */
function clsx(mixed ...$inputs): string
{
    if (isset($inputs[0]) && count($inputs) === 1 && is_string($inputs[0])) {
        return $inputs[0];
    }

    $classes = [];

    foreach ($inputs as $input) {
        Internal\appendClasses($input, $classes);
    }

    return implode(' ', $classes);
}

namespace Cn\Internal;

/**
 * @internal
 *
 * @param  list<string>  $classes
 */
function appendClasses(mixed $value, array &$classes): void
{
    if (is_string($value)) {
        if ($value !== '') {
            $classes[] = $value;
        }
    } elseif (is_array($value)) {
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                if ($item && $key !== '') {
                    $classes[] = $key;
                }
            } else {
                appendClasses($item, $classes);
            }
        }
    } elseif ((is_int($value) || is_float($value)) && $value != 0) {
        $classes[] = (string) $value;
    } elseif ($value instanceof \Stringable) {
        $string = (string) $value;

        if ($string !== '') {
            $classes[] = $string;
        }
    }
}
