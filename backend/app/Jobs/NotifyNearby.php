<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Services\IncidentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Sends nearby alerts after a delay, skipping incidents that were undone, removed or hidden from the feed meanwhile. */
class NotifyNearby implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $incidentId) {}

    public function handle(IncidentService $svc): void
    {
        $i = Incident::public()->find($this->incidentId);
        if ($i) { $svc->notifyNearby($i); app(\App\Services\DispatchService::class)->dispatch($i); $svc->notifyModerators($i); }
    }
}
