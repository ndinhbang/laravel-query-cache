<?php

namespace Ndinhbang\QueryCache;

use Illuminate\Auth\EloquentUserProvider;

class CacheableEloquentUserProvider extends EloquentUserProvider
{
    /**
     * {@inheritDoc}
     */
    protected function newModelQuery($model = null)
    {
        return parent::newModelQuery($model)->cache();
    }
}
