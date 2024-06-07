<?php

namespace Ndinhbang\QueryCache;

use BadMethodCallException;
use DateInterval;
use DateTimeInterface;
use Illuminate\Cache\NoLock;
use Illuminate\Cache\RedisTagSet;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Traits\ForwardsCalls;
use LogicException;
use Psr\SimpleCache\InvalidArgumentException;

class DatabaseConnectionCacheProxy extends Connection
{
    use ForwardsCalls;

    public function __construct(
        public ConnectionInterface                        $connection,
        protected Repository                              $repository,
        protected array                                   $tags,
        protected DateTimeInterface|DateInterval|int|null $ttl,
        protected int                                     $lockWait,
        protected string                                  $cachePrefix,
        protected ?Relation                               $relation = null,
    )
    {
        if (empty($tags)) {
            throw new \InvalidArgumentException('Query cache tag must be provided.');
        }
    }

    /**
     * Create a new CacheAwareProxy instance.
     *
     * @param ConnectionInterface $connection
     * @param DateTimeInterface|\DateInterval|int|null $ttl
     * @param array $tags
     * @param int|null $wait
     * @param string|null $store
     * @param Relation|null $relation
     * @param string|null $cachePrefix
     * @return static
     */
    public static function createNewInstance(
        ConnectionInterface                     $connection,
        DateTimeInterface|DateInterval|int|null $ttl,
        array                                   $tags,
        int|null                                $wait,
        ?string                                 $store,
        ?Relation                               $relation = null,
        ?string                                 $cachePrefix = null
    ): static
    {
        [
            'ttl' => $defaultTtl,
            'lock_wait' => $defaultLockWait,
            'prefix' => $defaultCachePrefix,
        ] = config('query-cache');

        $wait = $wait ?? $defaultLockWait;

        return new static(
            $connection,
            static::store($store, (bool)$wait),
            $tags,
            $ttl ?? $defaultTtl,
            $wait,
            $cachePrefix ?: $defaultCachePrefix,
            $relation,
        );
    }

    /**
     * Run a select statement against the database.
     *
     * @param string $query
     * @param array $bindings
     * @param bool $useReadPdo
     * @return array
     * @throws InvalidArgumentException|\SodiumException
     */
    public function select($query, $bindings = [], $useReadPdo = true): array
    {
        // Create the unique hash for the query to avoid any duplicate query.
        $key = $this->cachePrefix . ':' . $this->getQueryHash($query, $bindings);
        // Retrieve the results from the cache.
        if (!is_null($results = $this->repository->get($key))) {
            return $results;
        }
        // Lock cache regeneration process to prevent cache stampede (thundering herd) problem.
        return $this
            ->retrieveLock($key)
            ->block($this->lockWait, function () use ($query, $bindings, $useReadPdo, $key): array {
                // Retrieve the results from the cache.
                if (!is_null($results = $this->repository->get($key))) {
                    return $results;
                }
                // Retrieve the results from the db.
                $results = $this->connection->select($query, $bindings, $useReadPdo);
                $this->repository->put($key, $results, $this->ttl);
                // Tagging cache key for delete later.
                $seconds = $this->getSeconds($this->ttl);
                if ($seconds > 0) {
                    $this->tags($this->collectTags())->addEntry($key, $seconds, 'NX');
                }
                return $results;
            });
    }

    /**
     * @return array
     */
    protected function collectTags(): array
    {
        if (! is_null($this->relation)) {
            // collect additional tag from relation
            $this->tags[] = $this->relation->getModel()->getTable();
            $this->tags[] = $this->relation->getParent()->getTable();
            if ($this->relation instanceof HasManyThrough) {
                $this->tags[] = $this->relation->getThroughParent()->getTable();
                $this->tags[] = $this->relation->getFarParent()->getTable();
            }
        }

        return array_unique($this->tags);
    }

    public function addTag(string $tag)
    {
        $this->tags[] = $tag;
    }

    /**
     * @param array $names
     * @return \Illuminate\Cache\RedisTagSet
     */
    protected function tags(array $names): RedisTagSet
    {
        return new RedisTagSet($this->repository->getStore(), $names);
    }

    /**
     * Run a select statement and return a single result.
     *
     * @param string $query
     * @param array $bindings
     * @param bool $useReadPdo
     * @return mixed
     * @throws InvalidArgumentException
     * @throws \SodiumException
     */
    public function selectOne($query, $bindings = [], $useReadPdo = true): mixed
    {
        $records = $this->select($query, $bindings, $useReadPdo);

        return array_shift($records);
    }

    /**
     * Hashes the incoming query for using as cache key.
     *
     * @param string $query
     * @param array $bindings
     * @return string
     * @throws \SodiumException
     */
    protected function getQueryHash(string $query, array $bindings): string
    {
        return rtrim(
            base64_encode(sodium_crypto_generichash(
                $this->connection->getDatabaseName() .
                $query .
                implode('', $bindings)
            )
        ), '=');
    }

    /**
     * Retrieves the lock to use before getting the results.
     *
     * @param string $key
     * @return \Illuminate\Contracts\Cache\Lock
     */
    protected function retrieveLock(string $key): Lock
    {
        if (!$this->lockWait) {
            return new NoLock($key, $this->lockWait);
        }
        // @phpstan-ignore-next-line
        return $this->repository->getStore()->lock($key, $this->lockWait);
    }

    /**
     * Pass-through all properties to the underlying connection.
     */
    public function __get(string $name): mixed
    {
        return $this->connection->{$name};
    }

    /**
     * Pass-through all properties to the underlying connection.
     * @noinspection MagicMethodsValidityInspection
     */
    public function __set(string $name, mixed $value): void
    {
        $this->connection->{$name} = $value;
    }

    /**
     * Pass-through all method calls to the underlying connection.
     */
    public function __call($method, $parameters)
    {
        return $this->forwardCallTo($this->connection, $method, $parameters);
    }

    protected static function store(?string $store, bool $lockable): Repository
    {
        $repository = cache()->store($store ?: config('query-cache.store'));

        if (!$repository->supportsTags()) {
            throw new BadMethodCallException("The [$store] cache does not support tagging.");
        }

        if ($lockable && !$repository->getStore() instanceof LockProvider) {
            $store = $store ?: cache()->getDefaultDriver();
            throw new LogicException("The [$store] cache does not support atomic locks.");
        }

        return $repository;
    }

    /**
     * Calculate the number of seconds for the given TTL.
     */
    protected function getSeconds(DateInterval|DateTimeInterface|int $ttl): int
    {
        $duration = $this->parseDateInterval($ttl);

        if ($duration instanceof DateTimeInterface) {
            $duration = Carbon::now()->diffInRealSeconds($duration, false);
        }

        return (int)max($duration, 0);
    }

    /**
     * If the given value is an interval, convert it to a DateTime instance.
     */
    protected function parseDateInterval($delay): DateInterval|DateTimeInterface|int
    {
        if ($delay instanceof DateInterval) {
            $delay = Carbon::now()->add($delay);
        }

        return $delay;
    }
}
