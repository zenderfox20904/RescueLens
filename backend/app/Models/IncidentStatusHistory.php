<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentStatusHistory extends Model
{
    protected $table = 'incident_status_history';
    protected $guarded = ['id'];
    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
}
