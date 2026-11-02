<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    protected $table = 'user_notifications';
    protected $guarded = ['id'];
    protected $casts = ['read_at' => 'datetime'];
    public function incident() { return $this->belongsTo(Incident::class); }
}
