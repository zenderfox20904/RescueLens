<?php

namespace App\Jobs;

use App\Models\CameraFrame;
use App\Services\Camera\CameraIngestService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AnalyzeCameraFrame implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $frameId) {}

    public function handle(CameraIngestService $svc): void
    {
        $f = CameraFrame::with(['feed', 'media'])->find($this->frameId);
        if ($f && $f->status === 'queued' && $f->media) $svc->analyze($f);
    }
}
