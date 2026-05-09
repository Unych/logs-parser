<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Importing\LogImporter;
use App\Services\Parsing\LogLineParser;
use App\Services\Parsing\UserAgentParser;
use App\Services\Queue\RedisLogQueue;
use App\Services\Stats\StatsAggregator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UserAgentParser::class);
        $this->app->singleton(LogLineParser::class);
        $this->app->singleton(RedisLogQueue::class);

        $this->app->singleton(LogImporter::class, function ($app) {
            return new LogImporter(
                parser: $app->make(LogLineParser::class),
                queue: $app->make(RedisLogQueue::class),
                batchSize: (int) config('logs_parser.batch_size', 1000),
            );
        });

        $this->app->singleton(StatsAggregator::class, function () {
            return new StatsAggregator(
                cacheTtl: (int) config('logs_parser.cache_ttl', 300),
            );
        });
    }

    public function boot(): void
    {
        RateLimiter::for('upload-logs', function (Request $request) {
            $limit = (int) config('logs_parser.upload_throttle_per_min', 10);
            return [
                Limit::perMinute($limit)->by($request->ip() ?? 'global'),
            ];
        });
    }
}
