<?php

namespace App\Services\Integrations;

use App\Models\Incident;
use Illuminate\Support\Facades\Http;

/**
 * Optional: publish a verified incident link to a Facebook Page via the Graph API
 * POST /{page-id}/feed (https://developers.facebook.com/docs/pages-api/posts).
 * Disabled by default (FB_PAGE_POSTING_ENABLED=false). Only a moderator can trigger it,
 * one incident at a time, after explicit confirmation in the UI. Never automatic.
 */
class FacebookPagePublisher
{
    public function enabled(): bool
    {
        $c = config('rescuelens.facebook_page');
        return ($c['enabled'] ?? false) && filled($c['page_id'] ?? null) && filled($c['page_token'] ?? null);
    }

    public function publish(Incident $i): array
    {
        $c = config('rescuelens.facebook_page');
        $link = rtrim(config('app.url'), '/').'/share/incidents/'.$i->public_id;
        $msg = "RescueLens community alert (moderator checked): {$i->title}\nArea: ".($i->area_name ?? 'see map')."\nIn danger? Call 999 (Bangladesh national emergency).";
        $res = Http::asForm()->timeout(10)->post("https://graph.facebook.com/{$c['graph_version']}/{$c['page_id']}/feed", [
            'message' => $msg, 'link' => $link, 'access_token' => $c['page_token'],
        ]);
        if ($res->failed()) {
            throw new \RuntimeException('Facebook Page post failed with HTTP '.$res->status());
        }
        return ['post_id' => $res->json('id')];
    }
}
