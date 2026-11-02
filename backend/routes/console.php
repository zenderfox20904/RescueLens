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

// Attach a photo to each seeded example post and run a real AI analysis on it with the configured provider.
// Posts become regular (non demo) posts only when the analysis completes. Photos and credits: database/seeders/photos.
Artisan::command('rescuelens:analyze-seeded {--force : Re-run even if a post already has a photo}', function (\App\Services\AnalysisPipeline $pipe, \App\Services\ImageProcessor $img) {
    $map = [
        'Two car collision on Dhaka Aricha Highway' => ['01', 'BD'], 'Knee deep water at Mirpur 10' => ['02', 'BD'],
        'Smoke from a building in Old Dhaka' => ['03', 'BD'], 'Fallen electric wire on footpath' => ['04', 'BD'],
        'Part of an old building collapsed' => ['05', 'BD'], 'Waterlogging under Minto Bridge' => ['06', 'IN'],
        'Shops on fire at Balogun Market' => ['07', 'NG'], 'Car crushed in junction crash' => ['08', 'GB'],
        'Ice on sidewalk causing falls' => ['09', 'US'], 'River overflow in Kampung Melayu' => ['10', 'ID'],
    ];
    foreach ($map as $title => [$file, $cc]) {
        $i = \App\Models\Incident::withTrashed()->whereIn('title', [$title, '[DEMO] '.$title])->first();
        if (! $i) { $this->warn("missing: {$title}"); continue; }
        if ($i->media()->exists() && ! $this->option('force')) { $this->line("skip: {$title}"); continue; }
        $path = database_path("seeders/photos/{$file}.jpg");
        $meta = $img->store(new \Illuminate\Http\UploadedFile($path, "{$file}.jpg", 'image/jpeg', null, true));
        $m = \App\Models\IncidentMedia::create($meta + ['user_id' => $i->user_id, 'incident_id' => $i->id, 'metadata_stripped' => true]);
        $a = $pipe->run($m, $i->user_id, 'en', $i->description, null, $cc);
        if ($a->status !== 'completed') { $this->error("analysis failed: {$title} ({$a->error_code})"); continue; }
        \App\Models\AiAnalysis::where('incident_id', $i->id)->where('mode', 'demo')->delete();
        $a->update(['incident_id' => $i->id]);
        $i->forceFill(['title' => $title, 'is_demo' => false, 'ai_assisted' => true,
            'severity' => in_array($a->severity, ['low', 'moderate', 'high', 'critical'], true) ? $a->severity : $i->severity])->save();
        $i->comments()->where('body', 'like', '%(demo comment)%')->update(['body' => 'I saw this too, please be careful.']);
        $i->history()->where('note', 'Checked by moderator (demo)')->update(['note' => 'Checked by moderator']);
        $this->info("ok: {$title} -> {$a->primary_hazard} {$a->severity} ({$a->latency_ms} ms)");
    }
})->purpose('Give seeded example posts real photos and live AI analyses');
