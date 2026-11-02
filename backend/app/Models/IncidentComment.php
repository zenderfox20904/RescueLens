<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Model;

class IncidentComment extends Model
{
    use SoftDeletes;
    protected $table = 'incident_comments';
    protected $guarded = ['id'];
    public function incident() { return $this->belongsTo(Incident::class); }
    public function user() { return $this->belongsTo(User::class); }
}
