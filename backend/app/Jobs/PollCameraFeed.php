<?php

namespace App\Jobs;

use App\Models\CameraFeed;
use App\Services\Camera\CameraIngestService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PollCameraFeed implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $feedId) {}

    public function handle(CameraIngestService $svc): void
    {
        $f = CameraFeed::find($this->feedId);
        if ($f && $f->is_active && $f->kind === 'snapshot_url') $svc->poll($f);
    }
}
