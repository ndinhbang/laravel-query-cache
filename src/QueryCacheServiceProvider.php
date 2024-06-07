<?php

namespace Ndinhbang\QueryCache;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Ndinhbang\QueryCache\Commands\ForgetCommand;
use Ndinhbang\QueryCache\Mixins\DatabaseCollectionMixin;
use Ndinhbang\QueryCache\Mixins\EloquentBuilderMixin;
use Ndinhbang\QueryCache\Mixins\HasManyThroughMixin;
use Ndinhbang\QueryCache\Mixins\QueryBuilderMixin;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class QueryCacheServiceProvider extends PackageServiceProvider
{
    public const STUBS = __DIR__ . '/../.stubs/query-cache.php';

    public function configurePackage(Package $package): void
    {
        $package
            ->name('query-cache')
            ->hasConfigFile()
            ->hasCommand(ForgetCommand::class);
    }

    /**
     * @throws \ReflectionException
     */
    public function boot()
    {
        HasManyThrough::mixin(new HasManyThroughMixin());

        if (!Builder::hasMacro('cache')) {
            Builder::mixin(new QueryBuilderMixin());
        }

        if (!EloquentBuilder::hasGlobalMacro('cache')) {
            EloquentBuilder::mixin(new EloquentBuilderMixin());
        }

        Auth::provider('cacheable_eloquent', function (\Illuminate\Contracts\Foundation\Application $app, array $config) {
            return new CacheableEloquentUserProvider($app['hash'], $config['model']);
        });

        if ($this->app->runningInConsole()) {
            $this->publishes([static::STUBS => $this->app->basePath('.stubs/query-cache.php')], 'phpstorm');
        }

        parent::boot();
    }
}
