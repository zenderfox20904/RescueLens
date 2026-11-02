<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Queue a snapshot poll for every active, due camera feed. Run with `php artisan schedule:work` plus `php artisan queue:work`.
Artisan::command('cameras:poll', function () {
    $n = 0;
    \App\Models\CameraFeed::where('is_active', true)->where('kind', 'snapshot_url')->get()
        ->filter(fn ($f) => ! $f->last_polled_at || $f->last_polled_at->lte(now()->subSeconds($f->interval_seconds)))
        ->each(function ($f) use (&$n) { \App\Jobs\PollCameraFeed::dispatch($f->id); $n++; });
    $this->info("Queued {$n} camera polls");
})->purpose('Queue snapshot polls for due camera feeds');

\Illuminate\Support\Facades\Schedule::command('cameras:poll')->everyMinute()->withoutOverlapping();
