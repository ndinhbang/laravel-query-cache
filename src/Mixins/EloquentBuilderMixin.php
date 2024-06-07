<?php

namespace Ndinhbang\QueryCache\Mixins;

use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilerContract;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Str;
use Ndinhbang\QueryCache\DatabaseConnectionCacheProxy;
use Ndinhbang\QueryCache\Scopes\CacheRelations;

class EloquentBuilderMixin
{
    public function cache()
    {
        return function (
            DateTimeInterface|DateInterval|int|null $ttl = null,
            string|array                            $tag = [],
            string                                  $store = null,
            int|null                                $wait = null,
            EloquentBuilerContract                  $relation = null,
        ): EloquentBuilder {
            /**@var \Illuminate\Database\Eloquent\Builder $this */
            if (!config('query-cache.enable')) {
                return $this;
            }

            $tag = $tag ?: $this->getModel()->getTable();

            $this->getQuery()->cache($ttl, $tag, $store, $wait, $relation);

            // This global scope is responsible for caching eager loaded relations.
            $this->withGlobalScope(
                CacheRelations::class,
                new CacheRelations($ttl, $tag, $store, $wait)
            );

            return $this;
        };
    }

    public function withAggregateCache(): \Closure
    {
        /**
         * @param array|string $relations
         * @param string $column
         * @param string|null $function
         * @param array $tags
         * @return \Illuminate\Database\Eloquent\Builder
         */
        return function (array|string $relations, string $column, string $function = null, array $tags = []): EloquentBuilder {
            /**@var \Illuminate\Database\Eloquent\Builder $this */
            if (empty($relations)) {
                return $this;
            }

            if (is_null($this->query->columns)) {
                $this->query->select([$this->query->from . '.*']);
            }

            $connection = $this->getQuery()->getConnection();

            if (! ($connection instanceof DatabaseConnectionCacheProxy)) {
                $this->query->cache(tag: $tags);
            }

            $relations = is_array($relations) ? $relations : [$relations];

            foreach ($this->parseWithRelations($relations) as $name => $constraints) {
                // First we will determine if the name has been aliased using an "as" clause on the name
                // and if it has we will extract the actual relationship name and the desired name of
                // the resulting column. This allows multiple aggregates on the same relationships.
                $segments = explode(' ', $name);

                unset($alias);

                if (count($segments) === 3 && Str::lower($segments[1]) === 'as') {
                    [$name, $alias] = [$segments[0], $segments[2]];
                }

                $relation = $this->getRelationWithoutConstraints($name);

                // add relation table name to cache tags
                $connection->addTag($relation->getRelated()->getTable());

                if ($function) {
                    $hashedColumn = $this->getRelationHashedColumn($column, $relation);

                    $wrappedColumn = $this->getQuery()->getGrammar()->wrap(
                        $column === '*' ? $column : $relation->getRelated()->qualifyColumn($hashedColumn)
                    );

                    $expression = $function === 'exists' ? $wrappedColumn : sprintf('%s(%s)', $function, $wrappedColumn);
                } else {
                    $expression = $column;
                }

                // Here, we will grab the relationship sub-query and prepare to add it to the main query
                // as a sub-select. First, we'll get the "has" query and use that to get the relation
                // sub-query. We'll format this relationship name and append this column if needed.
                $query = $relation->getRelationExistenceQuery(
                    $relation->getRelated()->newQuery(), $this, new Expression($expression)
                )->setBindings([], 'select');

                $query->callScope($constraints);

                $query = $query->mergeConstraintsFrom($relation->getQuery())->toBase();

                // If the query contains certain elements like orderings / more than one column selected
                // then we will remove those elements from the query so that it will execute properly
                // when given to the database. Otherwise, we may receive SQL errors or poor syntax.
                $query->orders = null;
                $query->setBindings([], 'order');

                if (count($query->columns) > 1) {
                    $query->columns = [$query->columns[0]];
                    $query->bindings['select'] = [];
                }

                // Finally, we will make the proper column alias to the query and run this sub-select on
                // the query builder. Then, we will return the builder instance back to the developer
                // for further constraint chaining that needs to take place on the query as needed.
                $alias ??= Str::snake(
                    preg_replace('/[^[:alnum:][:space:]_]/u', '', "$name $function $column")
                );

                if ($function === 'exists') {
                    $this->selectRaw(
                        sprintf('exists(%s) as %s', $query->toSql(), $this->getQuery()->grammar->wrap($alias)),
                        $query->getBindings()
                    )->withCasts([$alias => 'bool']);
                } else {
                    $this->selectSub(
                        $function ? $query : $query->limit(1),
                        $alias
                    );
                }
            }

            return $this;
        };
    }
}
