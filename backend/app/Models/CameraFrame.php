<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CameraFrame extends Model
{
    protected $guarded = ['id'];

    public function feed()
    {
        return $this->belongsTo(CameraFeed::class, 'camera_feed_id');
    }

    public function media()
    {
        return $this->belongsTo(IncidentMedia::class, 'media_id');
    }

    public function incident()
    {
        return $this->belongsTo(Incident::class);
    }
}
