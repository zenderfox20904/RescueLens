<?php

namespace App\Providers;

use App\Services\AI\DemoSegmentationProvider;
use App\Services\AI\DemoVisionProvider;
use App\Services\AI\ImageSegmentationProvider;
use App\Services\AI\MetaModelClient;
use App\Services\AI\MetaSamProvider;
use App\Services\AI\MetaVisionProvider;
use App\Services\AI\VisionAnalysisProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $live = fn () => filled(config('rescuelens.meta.api_key'));
        $this->app->bind(VisionAnalysisProvider::class, fn ($app) => $live() ? new MetaVisionProvider($app->make(MetaModelClient::class)) : new DemoVisionProvider);
        $this->app->bind(ImageSegmentationProvider::class, fn ($app) => $live() ? new MetaSamProvider($app->make(MetaModelClient::class)) : new DemoSegmentationProvider);
    }

    public function boot(): void
    {
        $key = fn (Request $r) => $r->user()?->id ?: $r->ip();
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute((int) config('rescuelens.rate_limit_api', 120))->by($key($r)));
        RateLimiter::for('auth', fn (Request $r) => [Limit::perMinute(10)->by($r->ip()), Limit::perMinute(5)->by(strtolower((string) $r->input('email')).$r->ip())]);
        RateLimiter::for('uploads', fn (Request $r) => [Limit::perMinute(10)->by($key($r)), Limit::perDay(100)->by($key($r))]);
        RateLimiter::for('ai', fn (Request $r) => [Limit::perMinute(6)->by($key($r)), Limit::perDay(60)->by($key($r))]);
        RateLimiter::for('write', fn (Request $r) => Limit::perMinute(30)->by($key($r)));
    }
}
