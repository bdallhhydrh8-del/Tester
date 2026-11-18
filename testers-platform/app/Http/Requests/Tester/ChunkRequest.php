<?php

namespace App\Http\Requests\Tester;

use App\Support\UploadSettings;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /tester/uploads/{id}/chunk : one slice of the file.
 *
 * Do not add image or mimetypes rules here. A single slice of an image is not a
 * valid image on its own. The whole file is checked once, in complete().
 * The index range and the exact chunk size depend on the session, so
 * ChunkedUploadService::storeChunk() checks them.
 */
class ChunkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the role:tester middleware on the route does the access check
    }

    public function rules(): array
    {
        $maxKb = (int) floor(app(UploadSettings::class)->chunkSizeBytes() / 1024);

        return [
            'chunk_index' => ['required', 'integer', 'min:0', 'max:65535'],
            'chunk' => ['required', 'file', 'max:'.$maxKb],
        ];
    }

    public function messages(): array
    {
        $invalid = __('uploads.upload_invalid');

        return [
            'chunk_index.required' => $invalid,
            'chunk_index.integer' => $invalid,
            'chunk_index.min' => $invalid,
            'chunk_index.max' => $invalid,
            'chunk.required' => $invalid,
            'chunk.file' => $invalid,
            'chunk.uploaded' => $invalid,
            'chunk.max' => $invalid,
        ];
    }
}