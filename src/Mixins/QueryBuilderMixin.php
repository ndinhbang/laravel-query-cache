<?php

namespace Ndinhbang\QueryCache\Mixins;

use DateInterval;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilerContract;
use Ndinhbang\QueryCache\DatabaseConnectionCacheProxy;

class QueryBuilderMixin
{
    public function cache(): \Closure
    {
        return function (
            DateTimeInterface|DateInterval|int|null $ttl = null,
            string|array                            $tag = [],
            string                                  $store = null,
            int|null                                $wait = null,
            EloquentBuilerContract                  $relation = null,
        ): Builder {
            /** @var \Illuminate\Database\Query\Builder $this */
            if (!config('query-cache.enable')) {
                return $this;
            }

            // @phpstan-ignore-next-line
            $this->connection = DatabaseConnectionCacheProxy::createNewInstance(
            // Avoid re-wrapping the connection into another proxy.
                $this->connection instanceof DatabaseConnectionCacheProxy
                    ? $this->connection->connection
                    : $this->connection,
                $ttl,
                (array)($tag ?: $this->from),
                $wait,
                $store,
                $relation,
            );

            return $this;
        };
    }
}
