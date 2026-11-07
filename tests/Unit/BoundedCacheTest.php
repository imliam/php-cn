<?php

use Cn\Support\BoundedCache;

it('caches empty results and numeric input keys', function () {
    $cache = new BoundedCache(2);
    $cache->set('0', '');

    expect($cache->get('0'))->toBe('')
        ->and($cache->get('missing'))->toBeNull();
});

it('evicts old entries while retaining recently accessed entries', function () {
    $cache = new BoundedCache(2);
    $cache->set('a', 'A');
    $cache->set('b', 'B');
    $cache->set('c', 'C');

    expect($cache->get('a'))->toBe('A');

    $cache->set('d', 'D');
    expect($cache->get('b'))->toBeNull()
        ->and($cache->get('a'))->toBe('A')
        ->and($cache->get('d'))->toBe('D');
});

it('can disable caching', function () {
    $cache = new BoundedCache(0);
    $cache->set('key', 'value');

    expect($cache->get('key'))->toBeNull();
});
