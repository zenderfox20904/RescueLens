<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentConfirmation extends Model
{
    protected $table = 'incident_confirmations';
    protected $guarded = ['id'];
    public function user() { return $this->belongsTo(User::class); }
}
