<?php

namespace Database\Factories;

use App\Models\StoredFile;
use App\Models\User;
use App\Support\UploadSettings;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ready-made files for seeds and tests (Dev 4 builds screenshots and results screens with it).
 *
 *   StoredFile::factory()->withImage()->attachedTo($issue)->create();
 *
 * @extends Factory<StoredFile>
 */
class StoredFileFactory extends Factory
{
    protected $model = StoredFile::class;

    public function definition(): array
    {
        return [
            'uploaded_by' => User::factory()->state(['role' => 'tester']),
            'path' => 'images/'.Str::random(40).'.png',
            'mime' => 'image/png',
            'size' => 1,
        ];
    }

    /** Attached to a response or an issue. */
    public function attachedTo(Model $owner): static
    {
        return $this->state(fn () => [
            'fileable_type' => $owner->getMorphClass(),
            'fileable_id' => $owner->getKey(),
        ]);
    }

    /** Also writes a real PNG to the private disk, so GET /files/{id} shows a picture. */
    public function withImage(int $width = 320, int $height = 200): static
    {
        return $this->afterCreating(function (StoredFile $file) use ($width, $height) {
            $bytes = self::png($width, $height);

            Storage::disk(app(UploadSettings::class)->disk())->put($file->path, $bytes);
            $file->update(['size' => strlen($bytes)]);
        });
    }

    /** A plain coloured rectangle, different each time so seeded files are easy to tell apart. */
    private static function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, random_int(60, 200), random_int(60, 200), random_int(60, 200)));

        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}