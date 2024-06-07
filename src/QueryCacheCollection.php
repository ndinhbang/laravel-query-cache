<?php

namespace Ndinhbang\QueryCache;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as BaseCollection;

class QueryCacheCollection extends Collection
{
    /**
     * @param array|string $relations
     * @param array $tags
     * @return $this
     */
    public function loadCache(array|string $relations, array $tags = []): static
    {
        if ($this->isEmpty() || empty($relations)) {
            return $this;
        }

        $tags = $tags ?: [$this->first()->getTable()];
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

    /**
     * @param array|string $relations
     * @param string $column
     * @param string|null $function
     * @param array $tags
     * @return $this
     */
    public function loadAggregateCache(array|string $relations, string $column, string $function = null, array $tags = []): static
    {
        if ($this->isEmpty() || empty($relations)) {
            return $this;
        }

        /**@var Model $first */
        $first = $this->first();
        $tags = $tags ?: [$first->getTable()];

        $models = $first->newModelQuery()
            ->whereKey($this->modelKeys())
            ->select($first->getKeyName())
            ->cache(tag: $tags) // add cache
            ->withAggregateCache($relations, $column, $function, $tags)
            ->get()
            ->keyBy($first->getKeyName());


        $attributes = Arr::except(
            array_keys($models->first()->getAttributes()),
            $models->first()->getKeyName()
        );

        $this->each(function ($model) use ($models, $attributes) {
            $extraAttributes = Arr::only($models->get($model->getKey())->getAttributes(), $attributes);

            $model->forceFill($extraAttributes)
                ->syncOriginalAttributes($attributes)
                ->mergeCasts($models->get($model->getKey())->getCasts());
        });

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

    /**
     * @param array|string $relations
     * @param array $tags
     * @return $this
     */
    public function loadMissingCache(array|string $relations, array $tags = []): static
    {
        if (is_string($relations)) {
            $relations = func_get_args();
        }

        $tags = $tags ?: [$this->first()->getTable()];

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
                $path[] = [$segment => $segment];
            }

            if (is_callable($value)) {
                $path[count($segments) - 1][end($segments)] = $value;
            }

            $this->loadMissingRelationCache($this, $path, $tags);
        }

        return $this;
    }

    /**
     * @param \Ndinhbang\QueryCache\QueryCacheCollection $models
     * @param array $path
     * @param array $tags
     * @return void
     */
    protected function loadMissingRelationCache(self $models, array $path, array $tags = []): void
    {
        $relation = array_shift($path);

        $name = explode(':', key($relation))[0];

        if (is_string(reset($relation))) {
            $relation = reset($relation);
        }

        $models->filter(fn(?Model $model) => !is_null($model) && !$model->relationLoaded($name))
            ->loadCache($relation, $tags);

        if (empty($path)) {
            return;
        }

        $models = $models->pluck($name)->whereNotNull();

        if ($models->first() instanceof BaseCollection) {
            $models = $models->collapse();
        }

        $this->loadMissingRelationCache(new static($models), $path, $tags);
    }

    /**
     * @param string $relation
     * @param array<array-key, (callable(\Illuminate\Database\Eloquent\Builder): mixed)|string> $relations
     * @return $this
     */
    public function loadMorphCache(string $relation, array $relations): static
    {
        $this->pluck($relation)
            ->filter()
            ->groupBy(fn($model) => get_class($model))
            ->each(fn($models, $className) => static::make($models)->loadCache($relations[$className] ?? []));

        return $this;
    }

    /**
     * @param string $relation
     * @param array<array-key, (callable(\Illuminate\Database\Eloquent\Builder): mixed)|string> $relations
     * @return $this
     */
    public function loadMorphCountCache(string $relation, array $relations): static
    {
        $this->pluck($relation)
            ->filter()
            ->groupBy(fn($model) => get_class($model))
            ->each(fn($models, $className) => static::make($models)->loadCountCache($relations[$className] ?? []));

        return $this;
    }
}
