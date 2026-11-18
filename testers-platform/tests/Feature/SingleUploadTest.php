<?php

namespace Tests\Feature;

use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SingleUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
    }

    public function test_a_tester_can_upload_an_image(): void
    {
        $tester = $this->tester();
        Sanctum::actingAs($tester);

        $fileId = $this->postJson('/api/tester/uploads', [
            'file' => UploadedFile::fake()->image('screenshot.jpg', 300, 200),
        ])
            ->assertCreated()
            ->json('data.file_id');

        $file = StoredFile::findOrFail($fileId);

        $this->assertSame('image/jpeg', $file->mime);
        $this->assertSame($tester->id, $file->uploaded_by);
        $this->assertNull($file->fileable_id); // not attached yet
        $this->assertTrue(Storage::disk('private')->exists($file->path));
        $this->assertStringEndsWith('.jpg', $file->path);
        $this->assertStringNotContainsString('screenshot', $file->path); // our name, not theirs
    }

    public function test_a_file_that_is_not_an_image_is_rejected(): void
    {
        Sanctum::actingAs($this->tester());

        $this->postJson('/api/tester/uploads', [
            'file' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ])->assertStatus(422);

        $this->assertDatabaseCount('files', 0);
    }

    public function test_text_renamed_to_png_is_rejected(): void
    {
        Sanctum::actingAs($this->tester());

        // In real requests the Form Request catches this from the content.
        // In tests the fake file reports its type from the name, so the service must catch it.
        $this->postJson('/api/tester/uploads', [
            'file' => UploadedFile::fake()->createWithContent('shot.png', 'this is plain text'),
        ])->assertStatus(422);

        $this->assertDatabaseCount('files', 0);
    }

    public function test_an_image_over_the_limit_is_rejected(): void
    {
        config(['uploads.max_mb' => 1]);
        Sanctum::actingAs($this->tester());

        $this->postJson('/api/tester/uploads', [
            'file' => UploadedFile::fake()->image('big.jpg')->size(2048), // reports 2 MB
        ])->assertStatus(422);

        $this->assertDatabaseCount('files', 0);
    }

    public function test_a_guest_cannot_upload(): void
    {
        $this->postJson('/api/tester/uploads', [
            'file' => UploadedFile::fake()->image('a.jpg'),
        ])->assertUnauthorized();
    }

    private function tester(): User
    {
        // Adjust to Dev 1's factory if it needs more fields (phone, tester_number...).
        return User::factory()->create(['role' => 'tester']);
    }
}