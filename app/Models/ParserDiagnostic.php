<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParserDiagnostic extends Model
{
    protected $guarded = [];

    protected $casts = ['warnings' => 'array', 'datasets' => 'array', 'context' => 'array', 'completed' => 'boolean'];
}
