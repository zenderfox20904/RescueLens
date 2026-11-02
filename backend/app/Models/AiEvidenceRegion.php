<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiEvidenceRegion extends Model
{
    protected $table = 'ai_evidence_regions';
    protected $guarded = ['id'];
    protected $casts = ['confidence' => 'float'];
}
