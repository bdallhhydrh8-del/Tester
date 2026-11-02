<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = ['name', 'type', 'link', 'note', 'status', 'created_by'];

    protected $attributes = [
        'status' => 'disabled',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProjectType::class,
            'status' => ProjectStatus::class,
        ];
    }

    
    // The one function that says whether testers can answer in this project.
     
    public function acceptsAnswers(): bool
    {
        return $this->status === ProjectStatus::Live;
    }

    public function cards()
    {
        return $this->hasMany(Card::class)->orderBy('position');
    }
}