<?php

namespace Ndinhbang\QueryCache\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Ndinhbang\QueryCache\QueryCache
 */
class QueryCache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Ndinhbang\QueryCache\QueryCache::class;
    }
}
