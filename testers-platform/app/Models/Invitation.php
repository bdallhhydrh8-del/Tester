<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Invitation extends Model
{
    public const ACTIVE = 'active';
    public const DISABLED = 'disabled';

    protected $fillable = ['name', 'token', 'status', 'created_by'];

    // Same default as the database, so a new object never has a null status
    protected $attributes = ['status' => self::ACTIVE];

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    public function projectIds(): Collection
    {
        return DB::table('invitation_project')
            ->where('invitation_id', $this->id)
            ->pluck('project_id');
    }
}