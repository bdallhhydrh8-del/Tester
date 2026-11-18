<?php

namespace App\Http\Requests\Tester;

use App\Support\UploadSettings;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /tester/uploads : one image in one request.
 * Larger files must use the chunked upload, so the limit here is the smaller of
 * the admin's maximum and the chunk threshold.
 */
class UploadImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the role:tester middleware on the route does the access check
    }

    public function rules(): array
    {
        $settings = app(UploadSettings::class);

        return [
            'file' => [
                'required',
                'file',
                // Checks the real content type, not the file name.
                'mimetypes:'.implode(',', $settings->allowedMimes()),
                'max:'.(int) floor($this->singleLimitBytes() / 1024), // KB
            ],
        ];
    }

    public function messages(): array
    {
        $tooLarge = __('uploads.file_too_large', [
            'max' => (int) floor($this->singleLimitBytes() / 1048576),
        ]);

        return [
            'file.required' => __('uploads.upload_invalid'),
            'file.file' => __('uploads.upload_invalid'),
            // PHP itself rejected the file (bigger than upload_max_filesize).
            'file.uploaded' => $tooLarge,
            'file.mimetypes' => __('uploads.file_type_not_allowed'),
            'file.max' => $tooLarge,
        ];
    }

    private function singleLimitBytes(): int
    {
        $settings = app(UploadSettings::class);

        return min($settings->maxBytes(), $settings->chunkThresholdBytes());
    }
}