<?php

namespace App\Services;

use App\Exceptions\UploadException;
use App\Models\StoredFile;
use App\Models\UploadSession;
use App\Models\User;
use App\Support\UploadSettings;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Resumable upload for images above the single-request size (FR-T12).
 *
 * Flow: init -> chunk (repeat, any order, safe to resend) -> complete.
 * A lost connection is handled by asking status() which chunks arrived.
 */
class ChunkedUploadService
{
    public function __construct(private UploadSettings $settings)
    {
    }

    private function disk(): Filesystem
    {
        return Storage::disk($this->settings->disk());
    }

    /** A session that belongs to someone else is a plain 404. */
    public function findForTester(int $id, User $tester): UploadSession
    {
        return UploadSession::where('tester_id', $tester->id)->findOrFail($id);
    }

    public function init(
        User $tester,
        string $originalName,
        string $mime,
        int $totalSize,
        int $totalChunks,
    ): UploadSession {
        if (! $this->settings->isAllowedMime($mime)) {
            throw new UploadException('file_type_not_allowed');
        }

        if ($totalSize <= 0 || $totalChunks !== $this->settings->chunksFor($totalSize)) {
            throw new UploadException('upload_invalid');
        }

        if ($totalSize > $this->settings->maxBytes()) {
            throw new UploadException('file_too_large', 422, ['max' => $this->settings->maxMb()]);
        }

        return UploadSession::create([
            'tester_id' => $tester->id,
            // Only kept as information. It is never used to build a path.
            'original_name' => Str::limit($originalName, 255, ''),
            'mime' => $mime,
            'total_size' => $totalSize,
            'total_chunks' => $totalChunks,
            'temp_path' => $this->settings->chunksDir().'/'.Str::random(40),
        ]);
    }

    public function storeChunk(UploadSession $session, int $index, UploadedFile $chunk): UploadSession
    {
        $this->ensureOpen($session);

        if (
            $index < 0
            || $index >= $session->total_chunks
            || $chunk->getSize() !== $this->expectedChunkSize($session, $index)
        ) {
            throw new UploadException('upload_invalid');
        }

        // putFileAs overwrites, so resending a chunk after a lost connection is safe.
        $this->disk()->putFileAs($session->temp_path, $chunk, $index.'.part');

        // The row is locked so two chunks arriving together cannot overwrite each other's record.
        return DB::transaction(function () use ($session, $index) {
            $locked = UploadSession::lockForUpdate()->findOrFail($session->id);
            $this->ensureOpen($locked);

            if (! $locked->hasChunk($index)) {
                $received = $locked->received_chunks;
                $received[] = $index;
                sort($received);
                $locked->received_chunks = $received;
                $locked->save();
            }

            return $locked;
        });
    }

    /** What the client needs in order to resume. */
    public function status(UploadSession $session): array
    {
        return [
            'status' => $session->status,
            'received_chunks' => $session->received_chunks,
            'total_chunks' => $session->total_chunks,
            'chunk_size' => $this->settings->chunkSizeBytes(),
        ];
    }

    /** Assemble, validate and store the file. Safe to call twice. */
    public function complete(UploadSession $session): StoredFile
    {
        $file = DB::transaction(function () use ($session) {
            $locked = UploadSession::lockForUpdate()->findOrFail($session->id);

            // Second call: return the same file instead of assembling again.
            if ($locked->status === UploadSession::COMPLETED && $locked->file) {
                return $locked->file;
            }

            $this->ensureOpen($locked);

            if (! $locked->isFullyReceived()) {
                throw new UploadException('upload_incomplete', 409);
            }

            $tmp = $this->assemble($locked);
            $storedPath = null;

            try {
                $mime = $this->validateAssembled($tmp, $locked);

                // The name and extension come from us and the detected type, never from the tester.
                $storedPath = $this->disk()->putFileAs(
                    $this->settings->filesDir(),
                    new File($tmp),
                    Str::random(40).'.'.$this->settings->extensionFor($mime),
                );

                $file = StoredFile::create([
                    'uploaded_by' => $locked->tester_id,
                    'path' => $storedPath,
                    'mime' => $mime,
                    'size' => filesize($tmp),
                ]);

                $locked->update([
                    'status' => UploadSession::COMPLETED,
                    'file_id' => $file->id,
                ]);

                return $file;
            } catch (Throwable $e) {
                // Do not leave an orphan file behind if anything after the write failed.
                if ($storedPath) {
                    $this->disk()->delete($storedPath);
                }
                throw $e;
            } finally {
                @unlink($tmp);
            }
        });

        $this->disk()->deleteDirectory($session->temp_path);

        return $file;
    }

    /** The tester abandons an upload that is still open. */
    public function cancel(UploadSession $session): void
    {
        if ($session->isOpen()) {
            $this->disk()->deleteDirectory($session->temp_path);
            $session->update(['status' => UploadSession::EXPIRED]);
        }
    }

    private function ensureOpen(UploadSession $session): void
    {
        $expired = $session->created_at->lt(now()->subHours($this->settings->sessionTtlHours()));

        if (! $session->isOpen() || $expired) {
            throw new UploadException('upload_closed', 409);
        }
    }

    /** Every chunk is exactly chunk_size, except the last one, which is the remainder. */
    private function expectedChunkSize(UploadSession $session, int $index): int
    {
        $size = $this->settings->chunkSizeBytes();

        return $index < $session->total_chunks - 1
            ? $size
            : $session->total_size - $size * ($session->total_chunks - 1);
    }

    /** Joins the chunks, in order, into one temporary file. Returns its path. */
    private function assemble(UploadSession $session): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'upl_');
        $out = fopen($tmp, 'wb');

        try {
            for ($i = 0; $i < $session->total_chunks; $i++) {
                $chunkPath = $session->temp_path.'/'.$i.'.part';

                if (! $this->disk()->exists($chunkPath)) {
                    throw new UploadException('upload_incomplete', 409);
                }

                $in = $this->disk()->readStream($chunkPath);
                stream_copy_to_stream($in, $out);
                fclose($in);
            }
        } catch (Throwable $e) {
            fclose($out);
            @unlink($tmp);
            throw $e;
        }

        fclose($out);

        return $tmp;
    }

    /** Checks the real file, not what the tester declared. Returns the detected MIME type. */
    private function validateAssembled(string $path, UploadSession $session): string
    {
        clearstatcache(true, $path);
        $size = filesize($path);

        if ($size !== $session->total_size) {
            throw new UploadException('upload_invalid');
        }

        // Checked again because an admin may have lowered the limit since init.
        if ($size > $this->settings->maxBytes()) {
            throw new UploadException('file_too_large', 422, ['max' => $this->settings->maxMb()]);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        if (! $this->settings->isAllowedMime((string) $mime) || @getimagesize($path) === false) {
            throw new UploadException('file_type_not_allowed');
        }

        return $mime;
    }
}