<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class InteractionEdge extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['first_interaction_at' => 'datetime', 'last_interaction_at' => 'datetime', 'computed_at' => 'datetime'];

    public function person()
    {
        return $this->belongsTo(SocialPerson::class, 'social_person_id');
    }
}
