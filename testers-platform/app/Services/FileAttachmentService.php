<?php

namespace App\Services;

use App\Exceptions\UploadException;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Attaches uploaded files (file_ids) to a response or an issue.
 * Shared with Dev 4, who uses it for screenshots on problem reports.
 *
 * A file can be attached only if it:
 *  - exists and was uploaded by this tester,
 *  - is not already attached to something else.
 * "Complete" needs no check: a files row only exists once the upload finished.
 */
class FileAttachmentService
{
    /**
     * All or nothing: if one file is not valid, none is attached.
     * Safe to call inside the caller's own transaction (for example submit).
     *
     * @param  array<int|string>  $fileIds
     * @return Collection<int, StoredFile>
     *
     * @throws UploadException  file_not_ready | file_already_attached (both 422)
     */
    public function attach(Model $owner, User $tester, array $fileIds): Collection
    {
        if (! $owner->exists) {
            throw new InvalidArgumentException('Save the owner before attaching files to it.');
        }

        $ids = array_values(array_unique(array_map('intval', $fileIds)));

        if ($ids === []) {
            return new Collection();
        }

        return DB::transaction(function () use ($owner, $tester, $ids) {
            // Locked, so two requests cannot attach the same file at the same moment.
            $files = StoredFile::whereIn('id', $ids)->lockForUpdate()->get();

            // An unknown id and another tester's file get the same answer,
            // so a tester cannot probe which file ids exist.
            $allMine = $files->count() === count($ids)
                && $files->every(fn (StoredFile $file) => (int) $file->uploaded_by === (int) $tester->id);

            if (! $allMine) {
                throw new UploadException('file_not_ready');
            }

            if ($files->contains(fn (StoredFile $file) => $file->isAttached())) {
                throw new UploadException('file_already_attached');
            }

            // getMorphClass() honours the morph map, so the table stores 'response' or 'issue'.
            StoredFile::whereIn('id', $ids)->update([
                'fileable_type' => $owner->getMorphClass(),
                'fileable_id' => $owner->getKey(),
            ]);

            return StoredFile::whereIn('id', $ids)->get();
        });
    }
}