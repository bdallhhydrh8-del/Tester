<?php

namespace App\Services;

use App\Contracts\UploadService;
use App\Exceptions\UploadException;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\UploadSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Stores one image sent in a single request.
 * Larger files go through ChunkedUploadService, which applies the same rules.
 */
class ImageUploadService implements UploadService
{
    public function __construct(private UploadSettings $settings)
    {
    }

    public function store(User $tester, UploadedFile $file): StoredFile
    {
        $localPath = $file->getRealPath();

        if (! $file->isValid() || $localPath === false) {
            throw new UploadException('upload_invalid');
        }

        if (filesize($localPath) > $this->settings->maxBytes()) {
            throw new UploadException('file_too_large', 422, ['max' => $this->settings->maxMb()]);
        }

        // The real type, read from the content. What the tester declared is ignored.
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($localPath);

        if (! $this->settings->isAllowedMime((string) $mime) || @getimagesize($localPath) === false) {
            throw new UploadException('file_type_not_allowed');
        }

        $disk = Storage::disk($this->settings->disk());

        // The name and extension come from us and the detected type, never from the tester.
        $storedPath = $disk->putFileAs(
            $this->settings->filesDir(),
            $file,
            Str::random(40).'.'.$this->settings->extensionFor($mime),
        );

        try {
            return StoredFile::create([
                'uploaded_by' => $tester->id,
                'path' => $storedPath,
                'mime' => $mime,
                'size' => filesize($localPath),
            ]);
        } catch (Throwable $e) {
            // Do not leave an orphan file behind if the row could not be saved.
            $disk->delete($storedPath);
            throw $e;
        }
    }
}