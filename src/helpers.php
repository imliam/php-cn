<?php

if (! function_exists('cn')) {
    function cn(mixed ...$inputs): string
    {
        return \Cn\cn(...$inputs);
    }
}

if (! function_exists('clsx')) {
    function clsx(mixed ...$inputs): string
    {
        return \Cn\clsx(...$inputs);
    }
}
