<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UploadSession extends Model
{
    public const OPEN = 'open';
    public const COMPLETED = 'completed';
    public const EXPIRED = 'expired';

    protected $fillable = [
        'tester_id',
        'original_name',
        'mime',
        'total_size',
        'total_chunks',
        'received_chunks',
        'status',
        'temp_path',
        'file_id',
    ];

    // The DB cannot default a JSON column, so the default lives here.
    protected $attributes = [
        'received_chunks' => '[]',
        'status' => self::OPEN,
    ];

    protected $casts = [
        'received_chunks' => 'array',
        'total_size' => 'integer',
        'total_chunks' => 'integer',
    ];

    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tester_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'file_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    public function hasChunk(int $index): bool
    {
        return in_array($index, $this->received_chunks, true);
    }

    /** True when every chunk from 0 to total_chunks - 1 has arrived. */
    public function isFullyReceived(): bool
    {
        return count(array_unique($this->received_chunks)) === $this->total_chunks;
    }
}