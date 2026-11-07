<?php

use Cn\TailwindMerge;

use function Cn\clsx;
use function Cn\cn;

it('joins class values without resolving conflicts in clsx', function () {
    expect(clsx('p-2', 'p-4', 'custom', 'custom'))->toBe('p-2 p-4 custom custom')
        ->and(clsx())->toBe('')
        ->and(cn())->toBe('')
        ->and(clsx(''))->toBe('');
});

it('combines nested arrays and conditional class maps in order', function () {
    expect(clsx(
        'button',
        ['p-2', ['text-sm', ['hidden' => false, 'inline-flex' => true]]],
        ['bg-blue-500' => true, 'opacity-50' => false],
        [7 => 'rounded', 'disabled:cursor-wait' => 1, 12 => ['shadow-sm']],
    ))->toBe('button p-2 text-sm inline-flex bg-blue-500 rounded disabled:cursor-wait shadow-sm');
});

it('uses PHP truthiness for conditional maps', function () {
    expect(clsx([
        'false' => false,
        'null' => null,
        'zero' => 0,
        'float-zero' => 0.0,
        'empty' => '',
        'string-zero' => '0',
        'empty-array' => [],
        'true' => true,
        'number' => -1,
        'string' => 'yes',
        'array' => ['anything'],
        'object' => new stdClass(),
        '' => true,
    ]))->toBe('true number string array object');
});

it('preserves string zero and nonzero numbers while omitting standalone booleans and numeric zero', function () {
    expect(clsx(null, false, true, 0, 0.0, '', '0', 1, -2, 1.5, ['nested', false, 0, '0']))
        ->toBe('0 1 -2 1.5 nested 0')
        ->and(clsx('0'))->toBe('0')
        ->and(cn(0, '0', false, ['p-2', 'p-4']))->toBe('0 p-4');
});

it('uses Stringable values and ignores unsupported objects', function () {
    $className = new class () implements Stringable {
        public function __toString(): string
        {
            return 'p-2';
        }
    };
    $empty = new class () implements Stringable {
        public function __toString(): string
        {
            return '';
        }
    };
    $zero = new class () implements Stringable {
        public function __toString(): string
        {
            return '0';
        }
    };

    expect(clsx($className, [$empty, $zero], new stdClass(), fn (): string => 'ignored'))->toBe('p-2 0')
        ->and(cn($className, 'p-4'))->toBe('p-4');
});

it('resolves Tailwind 4 conflicts after conditional composition', function () {
    expect(cn(
        'inline-flex p-2 text-sm',
        ['p-4' => true, 'p-8' => false],
        [['text-lg/7', ['hover:bg-red-500', 'hover:bg-blue-500']]],
        ['bg-(--brand)', 'bg-(--accent)'],
    ))->toBe('inline-flex p-4 text-lg/7 hover:bg-blue-500 bg-(--accent)');
});

it('preserves caller whitespace in clsx and normalizes it when merging', function () {
    expect(clsx('  p-2  ', "\tp-4\n"))->toBe("  p-2   \tp-4\n")
        ->and(cn('  p-2  ', "\tp-4\n"))->toBe('p-4');
});

it('leaves the existing merge argument semantics unchanged', function () {
    expect(TailwindMerge::instance()->merge(['p-2' => true, 'p-4' => false]))->toBe('1')
        ->and(cn(['p-2' => true, 'p-4' => false]))->toBe('p-2');
});


it('accepts named variadic arguments and associative argument unpacking as class values', function () {
    expect(clsx(classes: 'p-2'))->toBe('p-2')
        ->and(cn(base: 'p-2', overrides: ['p-4' => true]))->toBe('p-4')
        ->and(clsx(...['base' => 'p-2', 'conditional' => ['p-4' => true]]))->toBe('p-2 p-4')
        ->and(cn(...['base' => 'p-2', 'conditional' => ['p-4' => true]]))->toBe('p-4');
});
