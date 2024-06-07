<?php

return [
    'enable' => env('QUERY_CACHE_ENABLE', true),
    'store' => env('QUERY_CACHE_STORE'),
    'prefix' => env('QUERY_CACHE_PREFIX', 'q'),
    'lock_wait' => env('QUERY_CACHE_LOCK_WAIT', 5),
    'ttl' => env('QUERY_CACHE_TTL', 900),
];
