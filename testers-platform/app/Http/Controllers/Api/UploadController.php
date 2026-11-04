<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tester\ChunkRequest;
use App\Http\Requests\Tester\InitUploadRequest;
use App\Services\ChunkedUploadService;
use App\Support\UploadSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Contracts\UploadService;
use App\Http\Requests\Tester\UploadImageRequest;

/**
 * Chunked (resumable) uploads for testers.
 *
 * TODO: store() for single-image uploads is added once the UploadService
 * interface is agreed with Dev 4.
 */
class UploadController extends Controller
{
    public function __construct(
        private ChunkedUploadService $chunked,
        private UploadSettings $settings,
         private UploadService $images,
        
        
    ) {}

    /** POST /tester/uploads/init */
    public function init(InitUploadRequest $request): JsonResponse
    {
        $session = $this->chunked->init(
            $request->user(),
            $request->validated('original_name'),
            $request->validated('mime'),
            (int) $request->validated('total_size'),
            (int) $request->validated('total_chunks'),
        );

        return $this->respond([
            'id' => $session->id,
            'total_chunks' => $session->total_chunks,
            // The client must slice the file into pieces of exactly this size.
            'chunk_size' => $this->settings->chunkSizeBytes(),
        ], null, 201);
    }

    /** POST /tester/uploads/{id}/chunk */
    public function chunk(ChunkRequest $request, int $id): JsonResponse
    {
        $session = $this->chunked->findForTester($id, $request->user());

        $session = $this->chunked->storeChunk(
            $session,
            (int) $request->validated('chunk_index'),
            $request->file('chunk'),
        );

        return $this->respond($this->chunked->status($session));
    }

    /** GET /tester/uploads/{id} : which chunks arrived, used to resume. */
    public function status(Request $request, int $id): JsonResponse
    {
        $session = $this->chunked->findForTester($id, $request->user());

        return $this->respond($this->chunked->status($session));
    }

    /** POST /tester/uploads/{id}/complete */
    public function complete(Request $request, int $id): JsonResponse
    {
        $session = $this->chunked->findForTester($id, $request->user());
        $file = $this->chunked->complete($session);

        return $this->respond(['file_id' => $file->id]);
    }

    /** DELETE /tester/uploads/{id} : abandon an upload that is still open. */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $session = $this->chunked->findForTester($id, $request->user());
        $this->chunked->cancel($session);

        return $this->respond(null);
    }

    // Replace with Dev 1's response helper if the project has one.
    private function respond(mixed $data, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'message' => $message], $status);
    }

    /** POST /tester/uploads : one image in one request. */
    public function store(UploadImageRequest $request): JsonResponse
    {
        $file = $this->images->store($request->user(), $request->file('file'));

        return $this->respond(['file_id' => $file->id], null, 201);
    }
}
