<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['first_message_at' => 'datetime', 'last_message_at' => 'datetime'];

    public function participants()
    {
        return $this->belongsToMany(SocialPerson::class, 'conversation_participants')->withPivot('is_owner');
    }
}
