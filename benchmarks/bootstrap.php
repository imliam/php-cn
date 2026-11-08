<?php

// Use exactly the same loader for both revisions. Composer's development
// autoload files would otherwise inflate one revision's memory and startup cost.
spl_autoload_register(static function (string $class) use ($root): void {
    foreach (['Cn\\' => $root . '/src/', 'TailwindMerge\\' => $root . '/src/', 'Psr\\SimpleCache\\' => $root . '/vendor/psr/simple-cache/src/'] as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $file = $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    }
});
require $root . '/src/TailwindMerge.php';

// Keep historical snapshots and current releases on the same worker code path.
// This alias exists only in benchmark processes, never in the package itself.
$mergerClass = class_exists('Cn\\TailwindMerge', false)
    ? 'Cn\\TailwindMerge'
    : 'TailwindMerge\\TailwindMerge';
class_alias($mergerClass, 'Benchmark\\Merger');
