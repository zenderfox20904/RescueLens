<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AiAnalysis extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['result' => 'array', 'requires_human_review' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(fn ($m) => $m->public_id ??= (string) Str::ulid());
    }

    public function regions() { return $this->hasMany(AiEvidenceRegion::class, 'analysis_id'); }
    public function media() { return $this->belongsTo(IncidentMedia::class, 'media_id'); }
}
