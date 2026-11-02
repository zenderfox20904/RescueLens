<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\IncidentStatusHistory;
use App\Models\User;
use App\Models\UserNotification;

class IncidentService
{
    public static function distanceKm(float $a, float $b, float $c, float $d): float
    {
        $r = 6371;
        $dLat = deg2rad($c - $a); $dLng = deg2rad($d - $b);
        $x = sin($dLat / 2) ** 2 + cos(deg2rad($a)) * cos(deg2rad($c)) * sin($dLng / 2) ** 2;
        return 2 * $r * asin(min(1, sqrt($x)));
    }

    public function duplicates(string $category, float $lat, float $lng, ?int $excludeId = null)
    {
        $km = config('rescuelens.duplicate_radius_m') / 1000;
        // Includes AI detections hidden from the feed so repeated camera frames do not pile up.
        return Incident::whereIn('status', Incident::PUBLIC_STATUSES)->whereNull('merged_into_id')->where('category', $category)
            ->whereNotIn('status', ['resolved', 'expired'])
            ->where('created_at', '>=', now()->subHours(config('rescuelens.duplicate_window_hours')))
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->near($lat, $lng, $km + 0.2)->get()
            ->filter(fn ($i) => self::distanceKm($lat, $lng, $i->lat, $i->lng) <= $km)->values();
    }

    public function setStatus(Incident $i, string $to, ?User $actor, ?string $note = null): void
    {
        if ($i->status === $to) return;
        IncidentStatusHistory::create(['incident_id' => $i->id, 'actor_id' => $actor?->id, 'from_status' => $i->status, 'to_status' => $to, 'note' => $note]);
        $i->status = $to;
        if ($to === 'verified') {
            // Only a human moderator can verify; that review also clears the AI unverified label and allows feed display.
            $i->verified_at = now();
            $i->ai_unverified = false;
            $i->feed_eligible = true;
        }
        $i->save();

        $label = ['verified' => 'verified by a moderator', 'resolved' => 'marked resolved', 'corroborated' => 'corroborated by the community',
            'disputed' => 'disputed by the community', 'removed' => 'removed by moderation', 'expired' => 'marked expired'][$to] ?? $to;
        $ids = $i->confirmations()->pluck('user_id')->push($i->user_id)->unique()->reject(fn ($id) => $id === $actor?->id);
        foreach ($ids as $uid) {
            UserNotification::create(['user_id' => $uid, 'incident_id' => $i->id, 'type' => 'status',
                'title' => "\"{$i->title}\" was {$label}", 'body' => $note]);
        }
        if (in_array($to, ['verified', 'resolved'])) app(\App\Services\Integrations\WebhookDispatcher::class)->dispatch('incident.'.$to, $i);
    }

    /** Nearby radius alerts: notify users whose alert area covers the incident. */
    /** Admins and moderators get every new public report in their review queue, wherever it is. */
    public function notifyModerators(Incident $i): int
    {
        $n = 0;
        User::whereIn('role', ['moderator', 'admin'])->where('id', '!=', $i->user_id)->each(function ($u) use ($i, &$n) {
            UserNotification::firstOrCreate(['user_id' => $u->id, 'incident_id' => $i->id, 'type' => 'review'],
                ['title' => 'Review: '.strtoupper($i->severity ?? 'moderate').' '.$i->title, 'body' => 'New community report waiting for verification.']);
            $n++;
        });
        return $n;
    }

    public function notifyNearby(Incident $i): int
    {
        $n = 0;
        User::whereNotNull('alert_lat')->where('id', '!=', $i->user_id)->chunkById(500, function ($users) use ($i, &$n) {
            foreach ($users as $u) {
                $dist = self::distanceKm($u->alert_lat, $u->alert_lng, $i->public_lat, $i->public_lng);
                // Critical incidents reach twice the personal radius; preferences and quiet hours are respected.
                $radius = $u->alert_radius_km * ($i->severity === 'critical' ? 2 : 1);
                if ($dist <= $radius && $u->wantsAlert($i->category, $i->severity ?? 'moderate')) {
                    UserNotification::create(['user_id' => $u->id, 'incident_id' => $i->id, 'type' => 'nearby',
                        'title' => strtoupper($i->severity).': '.$i->title, 'body' => sprintf('About %.1f km from your alert area. Unverified community report.', $dist)]);
                    $n++;
                }
            }
        });
        return $n;
    }

    public function recountReactions(Incident $i, ?User $actor): void
    {
        $i->confirmations_count = $i->confirmations()->where('type', 'confirm')->count();
        $i->disputes_count = $i->confirmations()->where('type', 'dispute')->count();
        $i->save();
        if ($i->status === 'reported' && $i->confirmations_count >= 3 && $i->confirmations_count > $i->disputes_count) {
            $this->setStatus($i, 'corroborated', null, 'Automatically labeled after 3 community confirmations. Not an official verification.');
        } elseif (in_array($i->status, ['reported', 'corroborated']) && $i->disputes_count >= 3 && $i->disputes_count > $i->confirmations_count) {
            $this->setStatus($i, 'disputed', null, 'Automatically labeled after community disputes.');
        }
    }
}
