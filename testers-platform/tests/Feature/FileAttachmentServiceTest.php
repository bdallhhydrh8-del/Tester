<?php

namespace Tests\Feature;

use App\Exceptions\UploadException;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\FileAttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FileAttachmentServiceTest extends TestCase
{
    use RefreshDatabase;

    // Any saved model can be the owner. A user stands in until Response and Issue exist.

    public function test_attaches_the_testers_files_to_the_owner(): void
    {
        $tester = $this->tester();
        $owner = $this->tester();
        $a = $this->file($tester);
        $b = $this->file($tester);

        $attached = $this->service()->attach($owner, $tester, [$a->id, $b->id]);

        $this->assertCount(2, $attached);

        foreach ([$a, $b] as $file) {
            $file->refresh();
            $this->assertSame($owner->getMorphClass(), $file->fileable_type);
            $this->assertEquals($owner->id, $file->fileable_id);
        }
    }

    public function test_duplicate_ids_and_an_empty_list_are_fine(): void
    {
        $tester = $this->tester();
        $owner = $this->tester();
        $a = $this->file($tester);

        $this->assertCount(1, $this->service()->attach($owner, $tester, [$a->id, $a->id]));
        $this->assertCount(0, $this->service()->attach($owner, $tester, []));
    }

    public function test_another_testers_file_is_rejected_and_nothing_is_attached(): void
    {
        $tester = $this->tester();
        $other = $this->tester();
        $owner = $this->tester();
        $mine = $this->file($tester);
        $theirs = $this->file($other);

        $this->assertAttachFails('file_not_ready', fn () =>
            $this->service()->attach($owner, $tester, [$mine->id, $theirs->id])
        );

        // All or nothing: even the valid file stays unattached.
        $this->assertNull($mine->fresh()->fileable_id);
        $this->assertNull($theirs->fresh()->fileable_id);
    }

    public function test_an_unknown_file_id_is_rejected(): void
    {
        $tester = $this->tester();
        $owner = $this->tester();

        $this->assertAttachFails('file_not_ready', fn () =>
            $this->service()->attach($owner, $tester, [999999])
        );
    }

    public function test_a_file_already_attached_is_rejected(): void
    {
        $tester = $this->tester();
        $firstOwner = $this->tester();
        $secondOwner = $this->tester();
        $file = $this->file($tester);

        $this->service()->attach($firstOwner, $tester, [$file->id]);

        $this->assertAttachFails('file_already_attached', fn () =>
            $this->service()->attach($secondOwner, $tester, [$file->id])
        );

        // It still belongs to the first owner.
        $this->assertEquals($firstOwner->id, $file->fresh()->fileable_id);
    }

    // ---- helpers ----

    private function service(): FileAttachmentService
    {
        return app(FileAttachmentService::class);
    }

    private function assertAttachFails(string $code, callable $attempt): void
    {
        try {
            $attempt();
            $this->fail("Expected an UploadException with code $code.");
        } catch (UploadException $e) {
            $this->assertSame($code, $e->errorCode);
            $this->assertSame(422, $e->status);
        }
    }

    private function tester(): User
    {
        // Adjust to Dev 1's factory if it needs more fields (phone, tester_number...).
        return User::factory()->create(['role' => 'tester']);
    }

    private function file(User $uploader): StoredFile
    {
        return StoredFile::create([
            'uploaded_by' => $uploader->id,
            'path' => 'images/'.Str::random(10).'.png',
            'mime' => 'image/png',
            'size' => 1,
        ]);
    }
}