<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SocialPerson extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['metadata' => 'array', 'first_seen_at' => 'datetime', 'last_seen_at' => 'datetime'];

    public function conversations()
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants');
    }

    public function friendships()
    {
        return $this->hasMany(Friendship::class);
    }

    public function sourceInteractions()
    {
        return $this->hasMany(FacebookInteraction::class, 'source_person_id');
    }

    public function targetInteractions()
    {
        return $this->hasMany(FacebookInteraction::class, 'target_person_id');
    }
}
