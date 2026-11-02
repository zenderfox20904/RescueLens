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
        // AI_PROVIDER=llama: Meta Llama API (OpenAI compatible endpoint). It has no segmentation model, so evidence regions are skipped.
        if (config('rescuelens.ai_provider') === 'llama') {
            $this->app->bind(VisionAnalysisProvider::class, fn ($app) => new \App\Services\AI\Llama\LlamaVisionProvider($app->make(\App\Services\AI\Llama\OpenAiCompatClient::class)));
            $this->app->bind(ImageSegmentationProvider::class, fn () => new \App\Services\AI\NoSegmentationProvider);
            $this->app->bind(\App\Services\I18n\TextTranslator::class, fn ($app) => new \App\Services\AI\Llama\LlamaTextTranslator($app->make(\App\Services\AI\Llama\OpenAiCompatClient::class)));
            return;
        }
        // Default AI_PROVIDER=meta: Meta Model API. Without a key the app runs in a clearly labeled demo mode.
        $live = fn () => filled(config('rescuelens.meta.api_key'));
        $this->app->bind(VisionAnalysisProvider::class, fn ($app) => $live() ? new MetaVisionProvider($app->make(MetaModelClient::class)) : new DemoVisionProvider);
        $this->app->bind(ImageSegmentationProvider::class, fn ($app) => $live() ? new MetaSamProvider($app->make(MetaModelClient::class)) : new DemoSegmentationProvider);
        $this->app->bind(\App\Services\I18n\TextTranslator::class, fn ($app) => $live() ? new \App\Services\I18n\MetaTextTranslator($app->make(MetaModelClient::class)) : new \App\Services\I18n\NullTextTranslator);
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Gate::define('crud', [\App\Policies\CrudPolicy::class, 'manage']);
        $key = fn (Request $r) => $r->user()?->id ?: $r->ip();
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute((int) config('rescuelens.rate_limit_api', 120))->by($key($r)));
        RateLimiter::for('auth', fn (Request $r) => [Limit::perMinute(10)->by($r->ip()), Limit::perMinute(5)->by(strtolower((string) $r->input('email')).$r->ip())]);
        RateLimiter::for('uploads', fn (Request $r) => [Limit::perMinute(10)->by($key($r)), Limit::perDay(100)->by($key($r))]);
        RateLimiter::for('ai', fn (Request $r) => [Limit::perMinute(6)->by($key($r)), Limit::perDay(60)->by($key($r))]);
        RateLimiter::for('write', fn (Request $r) => Limit::perMinute(30)->by($key($r)));
    }
}
