<?php

namespace Tests\Feature;

use App\Jobs\CleanupFilesJob;
use App\Models\StoredFile;
use App\Models\UploadSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CleanupFilesJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
    }

    public function test_old_unattached_files_are_deleted_but_recent_and_attached_ones_stay(): void
    {
        $user = $this->tester();
        $oldUnattached = $this->makeFile($user, false, now()->subHours(25));
        $recentUnattached = $this->makeFile($user, false, now()->subHours(2));
        $oldAttached = $this->makeFile($user, true, now()->subHours(25));

        dispatch_sync(new CleanupFilesJob);

        $this->assertModelMissing($oldUnattached);
        $this->assertFalse(Storage::disk('private')->exists($oldUnattached->path));

        $this->assertModelExists($recentUnattached);
        $this->assertModelExists($oldAttached);
        $this->assertTrue(Storage::disk('private')->exists($oldAttached->path));
    }

    public function test_files_older_than_the_retention_period_are_deleted(): void
    {
        config(['uploads.retention_days' => 30]);

        $user = $this->tester();
        $tooOld = $this->makeFile($user, true, now()->subDays(31));
        $stillKept = $this->makeFile($user, true, now()->subDays(29));

        dispatch_sync(new CleanupFilesJob);

        $this->assertModelMissing($tooOld);
        $this->assertFalse(Storage::disk('private')->exists($tooOld->path));
        $this->assertModelExists($stillKept);
    }

    public function test_without_a_retention_period_attached_files_are_kept_forever(): void
    {
        config(['uploads.retention_days' => null]);

        $old = $this->makeFile($this->tester(), true, now()->subDays(400));

        dispatch_sync(new CleanupFilesJob);

        $this->assertModelExists($old);
        $this->assertTrue(Storage::disk('private')->exists($old->path));
    }

    public function test_stale_open_uploads_are_closed_and_their_chunks_removed(): void
    {
        $user = $this->tester();
        Storage::disk('private')->put('chunks/abc/0.part', 'x');

        $session = new UploadSession([
            'tester_id' => $user->id,
            'original_name' => 'a.png',
            'mime' => 'image/png',
            'total_size' => 10,
            'total_chunks' => 1,
            'temp_path' => 'chunks/abc',
        ]);
        $session->created_at = now()->subHours(25);
        $session->save();

        dispatch_sync(new CleanupFilesJob);

        $this->assertSame(UploadSession::EXPIRED, $session->fresh()->status);
        $this->assertFalse(Storage::disk('private')->exists('chunks/abc/0.part'));
    }

    // ---- helpers ----

    private function tester(): User
    {
        // Adjust to Dev 1's factory if it needs more fields (phone, tester_number...).
        return User::factory()->create(['role' => 'tester']);
    }

    private function makeFile(User $user, bool $attached, Carbon $createdAt): StoredFile
    {
        $path = 'images/'.Str::random(10).'.png';
        Storage::disk('private')->put($path, 'x');

        $file = new StoredFile([
            'uploaded_by' => $user->id,
            'path' => $path,
            'mime' => 'image/png',
            'size' => 1,
        ]);

        if ($attached) {
            // Pretend it belongs to a response. Nothing loads the relation in these tests.
            $file->fileable_type = 'response';
            $file->fileable_id = 999;
        }

        $file->created_at = $createdAt;
        $file->updated_at = $createdAt;
        $file->save();

        return $file;
    }
}