<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dispatch extends Model
{
    public const STATUSES = ['alerted', 'accepted', 'en_route', 'on_scene', 'resolved', 'declined'];
    protected $guarded = ['id'];
    protected $casts = ['distance_km' => 'float'];
    public function incident() { return $this->belongsTo(Incident::class); }
    public function organization() { return $this->belongsTo(Organization::class); }
}
