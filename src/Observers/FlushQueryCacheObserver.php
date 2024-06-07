<?php

namespace Ndinhbang\QueryCache\Observers;

use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Ndinhbang\QueryCache\QueryCache;

class FlushQueryCacheObserver implements ShouldHandleEventsAfterCommit
{
    public bool $afterCommit = true;

    public function created(Model $model): void
    {
        $this->invalidateQueryCache($model);
    }

    public function updated(Model $model): void
    {
        $this->invalidateQueryCache($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidateQueryCache($model);
    }

    public function forceDeleted(Model $model): void
    {
        $this->invalidateQueryCache($model);
    }

    public function restored(Model $model): void
    {
        $this->invalidateQueryCache($model);
    }

    protected function invalidateQueryCache(Model $model): void
    {
        app(QueryCache::class)->forget($model->getQueryCacheTags());
    }
}
