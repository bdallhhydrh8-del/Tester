<?php

namespace Tests\Feature;

use App\Models\StoredFile;
use App\Models\UploadSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChunkedUploadTest extends TestCase
{
    use RefreshDatabase;

    private const CHUNK = 1048576; // 1 MB, set below so a small image needs two chunks

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        config(['uploads.chunk_size_mb' => 1]);
    }

    public function test_full_chunked_upload(): void
    {
        Sanctum::actingAs($this->tester());
        $bytes = $this->noisePng();
        $slices = str_split($bytes, self::CHUNK);

        $id = $this->init($bytes)->json('data.id');

        foreach ($slices as $i => $slice) {
            $this->sendChunk($id, $i, $slice)->assertOk();
        }

        $this->getJson("/api/tester/uploads/$id")
            ->assertOk()
            ->assertJsonPath('data.received_chunks', array_keys($slices));

        $first = $this->postJson("/api/tester/uploads/$id/complete")->assertOk()->json('data.file_id');
        $second = $this->postJson("/api/tester/uploads/$id/complete")->assertOk()->json('data.file_id');

        // Calling complete twice returns the same file.
        $this->assertSame($first, $second);

        $file = StoredFile::findOrFail($first);
        $this->assertSame('image/png', $file->mime);
        $this->assertSame(strlen($bytes), $file->size);
        $this->assertNull($file->fileable_id); // not attached yet
        $this->assertTrue(Storage::disk('private')->exists($file->path));
        $this->assertSame(UploadSession::COMPLETED, UploadSession::find($id)->status);
    }

    public function test_resending_a_chunk_is_safe(): void
    {
        Sanctum::actingAs($this->tester());
        $bytes = $this->noisePng();
        $id = $this->init($bytes)->json('data.id');
        $first = str_split($bytes, self::CHUNK)[0];

        $this->sendChunk($id, 0, $first)->assertOk();
        $this->sendChunk($id, 0, $first)->assertOk()
            ->assertJsonPath('data.received_chunks', [0]);
    }

    public function test_complete_before_all_chunks_arrive_is_rejected(): void
    {
        Sanctum::actingAs($this->tester());
        $bytes = $this->noisePng();
        $id = $this->init($bytes)->json('data.id');

        $this->sendChunk($id, 0, str_split($bytes, self::CHUNK)[0])->assertOk();

        $this->postJson("/api/tester/uploads/$id/complete")
            ->assertStatus(409)
            ->assertJsonPath('code', 'upload_incomplete');
    }

    public function test_another_tester_cannot_touch_the_session(): void
    {
        Sanctum::actingAs($this->tester());
        $id = $this->init($this->noisePng())->json('data.id');

        Sanctum::actingAs($this->tester());
        $this->getJson("/api/tester/uploads/$id")->assertNotFound();
    }

    public function test_a_disguised_file_is_rejected_when_completed(): void
    {
        Sanctum::actingAs($this->tester());

        // Declared as a PNG, but the bytes are plain text.
        $bytes = str_repeat('not an image ', 10);
        $id = $this->init($bytes, 1)->json('data.id');
        $this->sendChunk($id, 0, $bytes)->assertOk();

        $this->postJson("/api/tester/uploads/$id/complete")
            ->assertStatus(422)
            ->assertJsonPath('code', 'file_type_not_allowed');

        $this->assertDatabaseCount('files', 0);
    }

    // ---- helpers ----

    private function tester(): User
    {
        // Adjust to Dev 1's factory if it needs more fields (phone, tester_number...).
        return User::factory()->create(['role' => 'tester']);
    }

    private function init(string $bytes, ?int $chunks = null)
    {
        return $this->postJson('/api/tester/uploads/init', [
            'original_name' => 'screenshot.png',
            'mime' => 'image/png',
            'total_size' => strlen($bytes),
            'total_chunks' => $chunks ?? (int) ceil(strlen($bytes) / self::CHUNK),
        ])->assertCreated();
    }

    private function sendChunk(int $id, int $index, string $bytes)
    {
        return $this->postJson("/api/tester/uploads/$id/chunk", [
            'chunk_index' => $index,
            'chunk' => UploadedFile::fake()->createWithContent("$index.part", $bytes),
        ]);
    }

    /** A real PNG of random pixels, so it stays above 1 MB and needs two chunks. */
    private function noisePng(int $side = 700): string
    {
        $image = imagecreatetruecolor($side, $side);

        for ($x = 0; $x < $side; $x++) {
            for ($y = 0; $y < $side; $y++) {
                imagesetpixel($image, $x, $y, random_int(0, 0xFFFFFF));
            }
        }

        ob_start();
        imagepng($image, null, 0);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
