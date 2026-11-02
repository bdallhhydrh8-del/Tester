<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Invitation extends Model
{
    protected $fillable = ['name', 'token', 'expires_at', 'stopped_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'stopped_at' => 'datetime',
        ];
    }

    /** active, stopped or expired */
    public function state(): string
    {
        if ($this->stopped_at !== null) {
            return 'stopped';
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return 'expired';
        }

        return 'active';
    }

    public function projectIds(): Collection
    {
        return DB::table('invitation_project')
            ->where('invitation_id', $this->id)
            ->pluck('project_id');
    }
}