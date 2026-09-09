<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ImportSession extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['warnings' => 'array', 'data_coverage' => 'array', 'privacy_mode' => 'boolean', 'started_at' => 'datetime', 'completed_at' => 'datetime'];

    public function owner()
    {
        return $this->belongsTo(SocialPerson::class, 'owner_person_id');
    }

    public function diagnostics()
    {
        return $this->hasMany(ParserDiagnostic::class);
    }
}
