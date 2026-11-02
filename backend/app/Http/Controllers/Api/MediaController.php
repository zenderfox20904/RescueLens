<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\IncidentMedia;
use App\Services\ImageProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    /** A photo, or a short video (metadata removed, one frame kept as the poster that Meta AI analyzes). */
    public function store(Request $r, ImageProcessor $img, \App\Services\VideoProcessor $vid)
    {
        $r->validate(['image' => 'required_without:video|file|max:'.config('rescuelens.upload_max_kb').'|mimes:jpg,jpeg,png,webp',
            'video' => 'required_without:image|file|max:'.config('rescuelens.video_max_kb').'|mimetypes:video/mp4,video/quicktime,video/webm,video/x-m4v']);
        if ($r->hasFile('video')) {
            $v = $vid->store($r->file('video'));
            $meta = $v['image'] + ['video_path' => $v['video_path'], 'video_seconds' => $v['video_seconds']];
        } else {
            $meta = $img->store($r->file('image'));
        }
        $m = IncidentMedia::create($meta + ['user_id' => $r->user()->id, 'metadata_stripped' => true]);
        AuditLog::record('media.upload', $m);
        return response()->json(['id' => $m->public_id, 'url' => $m->signedUrl(), 'video_url' => $m->videoUrl(), 'width' => $m->width, 'height' => $m->height, 'metadata_stripped' => true], 201);
    }

    public function video(string $media)
    {
        $m = IncidentMedia::where('public_id', $media)->whereNotNull('video_path')->firstOrFail();
        return response()->file(Storage::disk('local')->path($m->video_path), ['Content-Type' => 'video/mp4', 'Cache-Control' => 'private, max-age=600', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** Served only through short lived signed URLs. */
    public function show(string $media)
    {
        $m = IncidentMedia::where('public_id', $media)->firstOrFail();
        return response(Storage::disk($m->disk)->get($m->path), 200, [
            'Content-Type' => $m->mime, 'Cache-Control' => 'private, max-age=600', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
