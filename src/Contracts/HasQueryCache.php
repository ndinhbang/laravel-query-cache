<?php

namespace Ndinhbang\QueryCache\Contracts;

interface HasQueryCache
{
    public function newCollection(array $models = []): \Ndinhbang\QueryCache\QueryCacheCollection;
    public function getQueryCacheTags(): array;
    public function getModelCacheKey(): string;
    public function loadCache(array|string $relations): static;
    public function loadMorphCache(string $relation, array $relations): static;
    public function loadMissingCache(array|string $relations): static;
    public function loadAggregateCache(array|string $relations, string $column, string $function = null): static;
    public function loadCountCache(array|string $relations): static;
    public function loadMaxCache(array|string $relations, string $column): static;
    public function loadMinCache(array|string $relations, string $column): static;
    public function loadSumCache(array|string $relations, string $column): static;
    public function loadAvgCache(array|string $relations, string $column): static;
    public function loadExistsCache(array|string $relations): static;
    public function loadMorphAggregateCache(string $relation, array $relations, string $column, string $function = null, array $tags = []): static;
    public function loadMorphCountCache(string $relation, array $relations, array $tags = []): static;
    public function loadMorphMaxCache(string $relation, array $relations, string $column, array $tags = []): static;
    public function loadMorphMinCache(string $relation, array $relations, string $column, array $tags = []): static;
    public function loadMorphSumCache(string $relation, array $relations, string $column, array $tags = []): static;
    public function loadMorphAvgCache(string $relation, array $relations, string $column, array $tags = []): static;
}
