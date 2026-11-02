<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Model;

class EmergencyResource extends Model
{
    use SoftDeletes;
    protected $table = 'emergency_resources';
    protected $guarded = ['id'];
    protected $casts = ['is_active' => 'boolean', 'verified_at' => 'datetime', 'last_reviewed_at' => 'datetime'];
}
