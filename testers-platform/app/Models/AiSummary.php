<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiSummary extends Model
{
    protected $guarded = [];

    protected $casts = ['generated_at' => 'datetime'];
}
