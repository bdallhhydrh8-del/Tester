<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StoredFile;
use App\Support\UploadSettings;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET /files/{id} : shows an uploaded image to admins.
 * Files live on a private disk, outside the web root, so this is the only way to reach them.
 */
class FileController extends Controller
{
    public function show(int $id, UploadSettings $settings): StreamedResponse
    {
        $file = StoredFile::findOrFail($id);

        // A file with no owner is part of a tester's unfinished upload, not evidence.
        abort_unless($file->isAttached(), 404);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($settings->disk());

        // The row can outlive the file if a delete failed or the file was cleaned up.
        abort_unless($disk->exists($file->path), 404);

        $extension = pathinfo($file->path, PATHINFO_EXTENSION);

        return $disk->response($file->path, 'file-'.$file->id.'.'.$extension, [
            // The type saved at upload time (detected from the content), never a guess.
            'Content-Type' => $file->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=300',
        ], 'inline');
    }
}