<?php

namespace App\Jobs;

use App\Models\StoredFile;
use App\Models\UploadSession;
use App\Support\UploadSettings;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Nightly cleanup of uploaded files. Scheduled in routes/console.php.
 *
 *  1. Files older than the retention period (if one is set).
 *  2. Files uploaded but never attached to a response or issue, after 24 hours.
 *  3. Chunked uploads left unfinished, and old finished session rows.
 */
class CleanupFilesJob implements ShouldQueue
{
    use Queueable;

    private UploadSettings $settings;

    public function handle(UploadSettings $settings): void
    {
        $this->settings = $settings;

        Log::info('Upload cleanup finished', [
            'unattached' => $this->purgeUnattached(),
            'sessions_expired' => $this->expireStaleSessions(),
        ]);

        $this->deleteOldSessionRows();
    }

    private function disk(): Filesystem
    {
        return Storage::disk($this->settings->disk());
    }

 

    /** Files that were uploaded but never used. */
    private function purgeUnattached(): int
    {
        $count = 0;

        StoredFile::unattached()
            ->where('created_at', '<', now()->subHours($this->settings->unattachedTtlHours()))
            ->chunkById(200, function ($files) use (&$count) {
                foreach ($files as $file) {
                    $count += $this->purge($file, onlyIfUnattached: true) ? 1 : 0;
                }
            });

        return $count;
    }

    /**
     * Deletes the file from the disk and its row. The row is locked and re-read first,
     * so a file attached a moment ago by a submit is never removed.
     * If the disk delete fails, the row stays and the next run tries again.
     */
    private function purge(StoredFile $file, bool $onlyIfUnattached): bool
    {
        try {
            return DB::transaction(function () use ($file, $onlyIfUnattached) {
                $current = StoredFile::lockForUpdate()->find($file->id);

                if (! $current || ($onlyIfUnattached && $current->isAttached())) {
                    return false;
                }

                $this->disk()->delete($current->path);
                $current->delete();

                return true;
            });
        } catch (Throwable $e) {
            report($e); // one bad file must not stop the whole job

            return false;
        }
    }

    /** Open uploads older than the session limit: remove their chunks and close them. */
    private function expireStaleSessions(): int
    {
        $count = 0;

        UploadSession::where('status', UploadSession::OPEN)
            ->where('created_at', '<', now()->subHours($this->settings->sessionTtlHours()))
            ->chunkById(200, function ($sessions) use (&$count) {
                foreach ($sessions as $session) {
                    try {
                        $this->disk()->deleteDirectory($session->temp_path);
                        $session->update(['status' => UploadSession::EXPIRED]);
                        $count++;
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });

        return $count;
    }

    /** Finished or cancelled sessions are only bookkeeping; drop them after a few days. */
    private function deleteOldSessionRows(): void
    {
        UploadSession::whereIn('status', [UploadSession::COMPLETED, UploadSession::EXPIRED])
            ->where('updated_at', '<', now()->subDays($this->settings->sessionKeepDays()))
            ->delete();
    }
}