<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AnalyticsExport extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['filters' => 'array', 'completed_at' => 'datetime', 'expires_at' => 'datetime'];
}
