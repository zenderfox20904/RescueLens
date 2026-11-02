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
    public function store(Request $r, ImageProcessor $img)
    {
        $r->validate(['image' => 'required|file|max:'.config('rescuelens.upload_max_kb').'|mimes:jpg,jpeg,png,webp']);
        $meta = $img->store($r->file('image'));
        $m = IncidentMedia::create($meta + ['user_id' => $r->user()->id, 'metadata_stripped' => true]);
        AuditLog::record('media.upload', $m);
        return response()->json(['id' => $m->public_id, 'url' => $m->signedUrl(), 'width' => $m->width, 'height' => $m->height, 'metadata_stripped' => true], 201);
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
