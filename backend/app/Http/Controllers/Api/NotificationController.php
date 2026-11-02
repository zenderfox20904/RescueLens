<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $r)
    {
        $q = UserNotification::where('user_id', $r->user()->id)->with('incident:id,public_id');
        return [
            'unread' => (clone $q)->whereNull('read_at')->count(),
            'data' => $q->latest()->limit(30)->get()->map(fn ($n) => ['id' => $n->id, 'type' => $n->type, 'title' => $n->title, 'body' => $n->body,
                'incident_id' => $n->incident?->public_id, 'read' => $n->read_at !== null, 'created_at' => $n->created_at->toIso8601String()]),
        ];
    }

    public function readAll(Request $r)
    {
        UserNotification::where('user_id', $r->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        return response()->noContent();
    }
}
