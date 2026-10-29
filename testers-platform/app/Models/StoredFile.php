<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Named StoredFile because "File" clashes with Laravel's File facade.
 */
class StoredFile extends Model
{
    protected $table = 'files';

    protected $fillable = [
        'fileable_type',
        'fileable_id',
        'uploaded_by',
        'path',
        'mime',
        'size',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    /** The response or issue this file is attached to (null until attached). */
    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isAttached(): bool
    {
        return $this->fileable_id !== null;
    }

    public function scopeUnattached(Builder $query): Builder
    {
        return $query->whereNull('fileable_id');
    }
}