<?php

namespace App\Contracts;

use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;

interface UploadService
{
    /**
     * Validates one image and stores it on the private disk.
     * Returns a new StoredFile that is not attached to anything yet;
     * attach it later with FileAttachmentService.
     *
     * @throws \App\Exceptions\UploadException  file_too_large | file_type_not_allowed | upload_invalid (all 422)
     */
    public function store(User $tester, UploadedFile $file): StoredFile;
}