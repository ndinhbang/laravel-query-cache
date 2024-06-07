<?php

namespace Illuminate\Database\Eloquent\Relations {

    use Illuminate\Database\Eloquent\Model;

    class HasManyThrough
    {
        /**
         * @source vendor/ndinhbang/laravel-query-cache/src/QueryCacheServiceProvider.php:33
         */
        public function getThroughParent(): Model
        {
            //
        }

        public function getFarParent(): Model
        {
            //
        }
    }
}

namespace Illuminate\Database\Query {

    use DateInterval;
    use DateTimeInterface;
    use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;

    class Builder
    {
        /**
         * Caches the underlying query results.
         *
         * @param \DateTimeInterface|\DateInterval|int|bool|null $ttl
         * @param string|array $tag
         * @param string|null $store
         * @param int|null $wait
         * @param \Illuminate\Contracts\Database\Eloquent\Builder|null $relation
         * @return static
         */
        public function cache(
            DateTimeInterface|DateInterval|int|bool|null $ttl = null,
            string|array                                 $tag = [],
            string                                       $store = null,
            int                                          $wait = null,
            BuilderContract                              $relation = null,
        ): static
        {
            //
        }
    }
}

namespace Illuminate\Database\Eloquent {

    use DateInterval;
    use DateTimeInterface;
    use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;

    class Builder
    {
        /**
         * Caches the underlying query results.
         *
         * @param \DateTimeInterface|\DateInterval|int|bool|null $ttl
         * @param string|array $tag
         * @param string|null $store
         * @param int|null $wait
         * @param \Illuminate\Contracts\Database\Eloquent\Builder|null $relation
         * @return static
         * @see \Ndinhbang\QueryCache\Mixins\EloquentBuilderMixin
         */
        public function cache(
            DateTimeInterface|DateInterval|int|bool|null $ttl = null,
            string|array                                 $tag = [],
            string                                       $store = null,
            int                                          $wait = null,
            BuilderContract                              $relation = null,
        ): static
        {
            //
        }

        /**
         * @param array|string $relations
         * @param string $column
         * @param string|null $function
         * @param array $tags
         * @return \Illuminate\Database\Eloquent\Builder
         * @see \Ndinhbang\QueryCache\Mixins\EloquentBuilderMixin
         */
        public function withAggregateCache(
            array|string $relations,
            string $column,
            string $function = null,
            array $tags = []
        ): static
        {
            //
        }
    }
}
