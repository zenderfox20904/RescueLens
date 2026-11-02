<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentComment extends Model
{
    protected $table = 'incident_comments';
    protected $guarded = ['id'];
    public function user() { return $this->belongsTo(User::class); }
}
