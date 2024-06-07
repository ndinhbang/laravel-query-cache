<?php

namespace Ndinhbang\QueryCache\Concerns;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Ndinhbang\QueryCache\Observers\FlushQueryCacheObserver;
use Ndinhbang\QueryCache\QueryCacheCollection;

trait QueryCacheable
{
    public static function bootQueryCacheable(): void
    {
        static::observe(FlushQueryCacheObserver::class);
    }

    public function getQueryCacheTags(): array
    {
        /** @var \Illuminate\Database\Eloquent\Model $this */
        return [
            $this->getTable(),
            $this->getTable() . '_' . $this->getRouteKey(),
            $this->getTable() . '_' . $this->getKey(),
        ];
    }

    public function getModelCacheKey(): string
    {
        return $this->getTable() . ':' . $this->getKey();
    }

    /**
     * @param array $models
     * @return \Ndinhbang\QueryCache\QueryCacheCollection
     */
    public function newCollection(array $models = []): QueryCacheCollection
    {
        return new QueryCacheCollection($models);
    }

    public function loadCache(array|string $relations, array $tags = []): static
    {
        if (empty($relations)) {
            return $this;
        }

        $tags = $tags ?: [$this->getModelCacheKey()];
        $relations = (array)$relations;
        $results = [];

        foreach ($relations as $key => $value) {
            if (is_numeric($key)) {
                $key = $value;
            }

            $segments = explode('.', explode(':', $key)[0]);

            if (str_contains($key, ':')) {
                $segments[count($segments) - 1] .= ':' . explode(':', $key)[1];
            }

            $path = [];

            foreach ($segments as $segment) {
                $path[] = $segment;

                if (!isset($results[$last = implode('.', $path)])) {
                    $results[$last] = function (Builder $query) use ($tags) {
                        $query->cache(tag: array_merge($tags, [
                            $query->getModel()->getTable(),
                        ]));
                    };
                }
            }

            if (is_callable($value)) {
                $results[$key] = function (Builder $query) use ($tags, $value) {
                    $query->cache(tag: array_merge($tags, [
                        $query->getModel()->getTable(),
                    ]));

                    $value($query);
                };
            }
        }

        return $this->load($results);
    }

    public function loadMorphCache(string $relation, array $relations, array $tags = []): static
    {
        if (!$this->{$relation}) {
            return $this;
        }

        $className = get_class($this->{$relation});

        // collect cache tags
        $tags = $tags ?: [$this->getModelCacheKey()];
        $tags[] = $this->{$relation}->getModel()->getTable();

        $this->{$relation}->loadCache($relations[$className] ?? [], $tags);

        return $this;
    }

    public function loadMissingCache(array|string $relations, array $tags = []): static
    {
        $relations = is_string($relations) ? func_get_args() : $relations;
        $tags = $tags ?: [$this->getModelCacheKey()];

        $this->newCollection([$this])->loadMissingCache($relations, $tags);

        return $this;
    }

    public function loadAggregateCache(
        array|string $relations,
        string       $column,
        string       $function = null,
        array        $tags = []
    ): static
    {
        $tags = $tags ?: [$this->getModelCacheKey()];
        $this->newCollection([$this])->loadAggregateCache($relations, $column, $function, $tags);

        return $this;
    }

    public function loadCountCache(array|string $relations, array $tags = []): static
    {
        return $this->loadAggregateCache($relations, '*', 'count', $tags);
    }

    public function loadMaxCache(array|string $relations, string $column, array $tags = []): static
    {
        return $this->loadAggregateCache($relations, $column, 'max', $tags);
    }

    public function loadMinCache(array|string $relations, string $column, array $tags = []): static
    {
        return $this->loadAggregateCache($relations, $column, 'min', $tags);
    }

    public function loadSumCache(array|string $relations, string $column, array $tags = []): static
    {
        return $this->loadAggregateCache($relations, $column, 'sum', $tags);
    }

    public function loadAvgCache(array|string $relations, string $column, array $tags = []): static
    {
        return $this->loadAggregateCache($relations, $column, 'avg', $tags);
    }

    public function loadExistsCache(array|string $relations, array $tags = []): static
    {
        return $this->loadAggregateCache($relations, '*', 'exists', $tags);
    }


    public function loadMorphAggregateCache(
        string $relation,
        array  $relations,
        string $column,
        string $function = null,
        array  $tags = []
    ): static
    {
        if (!$this->{$relation}) {
            return $this;
        }

        $className = get_class($this->{$relation});

        // collect cache tags
        $tags = $tags ?: [$this->getModelCacheKey()];
        $tags[] = $this->{$relation}->getModel()->getTable();

        $this->{$relation}->loadAggregateCache($relations[$className] ?? [], $column, $function, $tags);

        return $this;
    }

    public function loadMorphCountCache(string $relation, array $relations, array $tags = []): static
    {
        return $this->loadMorphAggregateCache($relation, $relations, '*', 'count', $tags);
    }

    public function loadMorphMaxCache(string $relation, array $relations, string $column, array $tags = []): static
    {
        return $this->loadMorphAggregateCache($relation, $relations, $column, 'max', $tags);
    }

    public function loadMorphMinCache(string $relation, array $relations, string $column, array $tags = []): static
    {
        return $this->loadMorphAggregateCache($relation, $relations, $column, 'min', $tags);
    }

    public function loadMorphSumCache(string $relation, array $relations, string $column, array $tags = []): static
    {
        return $this->loadMorphAggregateCache($relation, $relations, $column, 'sum', $tags);
    }

    public function loadMorphAvgCache(string $relation, array $relations, string $column, array $tags = []): static
    {
        return $this->loadMorphAggregateCache($relation, $relations, $column, 'avg', $tags);
    }

}
