<?php

namespace App\Http\Requests\Tester;

use App\Support\UploadSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * POST /tester/uploads/init : the tester announces a file before sending its chunks.
 */
class InitUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the role:tester middleware on the route does the access check
    }

    public function rules(): array
    {
        $settings = app(UploadSettings::class);

        return [
            'original_name' => ['required', 'string', 'max:255'],
            'mime' => ['required', 'string', Rule::in($settings->allowedMimes())],
            'total_size' => ['required', 'integer', 'min:1', 'max:'.$settings->maxBytes()],
            'total_chunks' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            // Only compare once both numbers are valid on their own.
            if ($v->errors()->hasAny(['total_size', 'total_chunks'])) {
                return;
            }

            $expected = app(UploadSettings::class)->chunksFor((int) $this->input('total_size'));

            if ((int) $this->input('total_chunks') !== $expected) {
                $v->errors()->add('total_chunks', __('uploads.upload_invalid'));
            }
        });
    }

    public function messages(): array
    {
        $invalid = __('uploads.upload_invalid');

        return [
            'original_name.required' => $invalid,
            'mime.required' => $invalid,
            'mime.in' => __('uploads.file_type_not_allowed'),
            'total_size.required' => $invalid,
            'total_size.integer' => $invalid,
            'total_size.min' => $invalid,
            'total_size.max' => __('uploads.file_too_large', [
                'max' => app(UploadSettings::class)->maxMb(),
            ]),
            'total_chunks.required' => $invalid,
            'total_chunks.integer' => $invalid,
            'total_chunks.min' => $invalid,
        ];
    }
}