<?php

namespace App\Support;

/**
 * Single place that answers "what are the upload rules right now?".
 *
 * Today it reads config/uploads.php. When Dev 2's settings table exists,
 * change only the private methods marked TODO; nothing that calls this class
 * needs to change.
 */
class UploadSettings
{
    public function disk(): string
    {
        return config('uploads.disk');
    }

    public function filesDir(): string
    {
        return trim(config('uploads.files_dir'), '/');
    }

    public function chunksDir(): string
    {
        return trim(config('uploads.chunks_dir'), '/');
    }

    /** Maximum size per image in MB, always kept between 1 and 20. */
    public function maxMb(): int
    {
        // TODO: read settings.upload_max_mb first once Dev 2's table exists.
        $value = (int) config('uploads.max_mb');

        return max(
            (int) config('uploads.min_allowed_mb'),
            min($value, (int) config('uploads.max_allowed_mb'))
        );
    }

    public function maxBytes(): int
    {
        return $this->maxMb() * 1024 * 1024;
    }

    /** @return array<string,string> MIME type => extension */
    public function allowedTypes(): array
    {
        // TODO: read settings.allowed_types (filtered against the config list).
        return config('uploads.allowed_types');
    }

    /** @return string[] */
    public function allowedMimes(): array
    {
        return array_keys($this->allowedTypes());
    }

    public function isAllowedMime(string $mime): bool
    {
        return array_key_exists($mime, $this->allowedTypes());
    }

    public function extensionFor(string $mime): ?string
    {
        return $this->allowedTypes()[$mime] ?? null;
    }

    /** Files larger than this must go through the chunked upload. */
    public function chunkThresholdBytes(): int
    {
        return (int) config('uploads.chunk_threshold_mb') * 1024 * 1024;
    }

    public function chunkSizeBytes(): int
    {
        return (int) config('uploads.chunk_size_mb') * 1024 * 1024;
    }

    /** Number of chunks a file of this size needs. */
    public function chunksFor(int $totalSize): int
    {
        return (int) ceil($totalSize / $this->chunkSizeBytes());
    }

    public function unattachedTtlHours(): int
    {
        return (int) config('uploads.unattached_ttl_hours');
    }

    public function sessionTtlHours(): int
    {
        return (int) config('uploads.session_ttl_hours');
    }

    /** Days to keep files before deletion. null means keep forever. */
    public function retentionDays(): ?int
    {
        // TODO: read settings.retention_days first once Dev 2's table exists.
        $days = config('uploads.retention_days');

        return $days !== null && (int) $days > 0 ? (int) $days : null;
    }

    public function sessionKeepDays(): int
    {
        return (int) config('uploads.session_keep_days');
    }
}