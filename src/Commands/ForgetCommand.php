<?php

namespace Ndinhbang\QueryCache\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Ndinhbang\QueryCache\QueryCache;

class ForgetCommand extends Command implements PromptsForMissingInput
{
    public $signature = 'query-cache:forget
                            {tags* : The tags of the queries to forget from the cache}
                            {--store= : The cache store to use}';

    public $description = 'Removes a cached query from the cache store.';

    /**
     * Indicates whether the command should be shown in the Artisan command list.
     */
    protected $hidden = false;

    public function handle(): int
    {
        $store = $this->option('store') ?: config('query-cache.store') ?? cache()->getDefaultDriver();

        $tags = array_map('trim', $this->argument('tags'));

        app(QueryCache::class)->store($store)->forget($tags);

        $this->comment('OK');

        return self::SUCCESS;
    }
}
