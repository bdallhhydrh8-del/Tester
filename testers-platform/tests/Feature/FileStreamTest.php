<?php

namespace Tests\Feature;

use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FileStreamTest extends TestCase
{
    use RefreshDatabase;

    private const BYTES = 'fake-image-bytes';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
    }

    public function test_an_admin_can_view_an_attached_file(): void
    {
        $file = $this->storedFile(attached: true);
        Sanctum::actingAs($this->user('admin'));

        $response = $this->get("/api/files/{$file->id}");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame(self::BYTES, $response->streamedContent());
    }

    public function test_a_tester_cannot_view_files(): void
    {
        $file = $this->storedFile(attached: true);
        Sanctum::actingAs($this->user('tester'));

        $this->getJson("/api/files/{$file->id}")->assertForbidden();
    }

    public function test_a_guest_cannot_view_files(): void
    {
        $file = $this->storedFile(attached: true);

        $this->getJson("/api/files/{$file->id}")->assertUnauthorized();
    }

    public function test_an_unattached_file_is_not_found(): void
    {
        $file = $this->storedFile(attached: false);
        Sanctum::actingAs($this->user('admin'));

        $this->getJson("/api/files/{$file->id}")->assertNotFound();
    }

    public function test_a_row_without_its_file_on_disk_is_not_found(): void
    {
        $file = $this->storedFile(attached: true);
        Storage::disk('private')->delete($file->path);
        Sanctum::actingAs($this->user('admin'));

        $this->getJson("/api/files/{$file->id}")->assertNotFound();
    }

    public function test_an_unknown_id_is_not_found(): void
    {
        Sanctum::actingAs($this->user('admin'));

        $this->getJson('/api/files/999999')->assertNotFound();
    }

    // ---- helpers ----

    private function user(string $role): User
    {
        // Adjust to Dev 1's factory if it needs more fields (username, password, phone...).
        return User::factory()->create(['role' => $role]);
    }

    private function storedFile(bool $attached): StoredFile
    {
        $uploader = $this->user('tester');
        $path = 'images/test-file.png';
        Storage::disk('private')->put($path, self::BYTES);

        $file = new StoredFile([
            'uploaded_by' => $uploader->id,
            'path' => $path,
            'mime' => 'image/png',
            'size' => strlen(self::BYTES),
        ]);

        if ($attached) {
            // Pretend it belongs to a response. Nothing loads the relation here.
            $file->fileable_type = 'response';
            $file->fileable_id = 1;
        }

        $file->save();

        return $file;
    }
}