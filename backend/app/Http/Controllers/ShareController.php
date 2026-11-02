<?php

namespace App\Http\Controllers;

use App\Models\Incident;

/**
 * Public share page with Open Graph tags (https://ogp.me, https://developers.facebook.com/docs/sharing/webmasters)
 * so links shared via the Facebook Share Dialog or WhatsApp render a preview. Humans are redirected to the SPA.
 * Only public, approximate data is exposed.
 */
class ShareController extends Controller
{
    public function incident(string $id)
    {
        $i = Incident::public()->with('media')->where('public_id', $id)->firstOrFail();
        $front = rtrim(config('rescuelens.frontend_url'), '/').'/incidents/'.$i->public_id;
        $img = $i->media->first()?->signedUrl(60 * 24 * 7) ?? rtrim(config('rescuelens.frontend_url'), '/').'/icons/og-default.png';
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $desc = mb_substr(($i->area_name ? $i->area_name.'. ' : '').($i->description ?? ''), 0, 200).' Community report, not an official confirmation. Emergency: 999.';
        $appId = config('rescuelens.facebook_app_id');
        $html = '<!doctype html><html lang="bn"><head><meta charset="utf-8"><title>'.$e($i->title).' | RescueLens</title>'
            .'<meta property="og:type" content="article"><meta property="og:site_name" content="RescueLens">'
            .'<meta property="og:title" content="'.$e($i->title).'"><meta property="og:description" content="'.$e($desc).'">'
            .'<meta property="og:image" content="'.$e($img).'"><meta property="og:url" content="'.$e(url()->current()).'">'
            .($appId ? '<meta property="fb:app_id" content="'.$e($appId).'">' : '')
            .'<meta name="twitter:card" content="summary_large_image"><meta http-equiv="refresh" content="0;url='.$e($front).'"></head>'
            .'<body><a href="'.$e($front).'">'.$e($i->title).'</a></body></html>';
        return response($html)->header('Cache-Control', 'public, max-age=60');
    }
}
