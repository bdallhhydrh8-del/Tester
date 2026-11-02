<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RespondsWithErrors;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCardRequest;
use App\Http\Requests\UpdateCardRequest;
use App\Http\Resources\CardResource;
use App\Models\Card;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class CardController extends Controller
{
    use RespondsWithErrors;

    // GET /projects/{project}/cards 
    public function index(Project $project): AnonymousResourceCollection
    {
        return CardResource::collection($project->cards)->additional(['message' => 'ok']);
    }

    // POST /projects/{project}/cards: added at the end 
    public function store(StoreCardRequest $request, Project $project): JsonResponse
    {
        $card = DB::transaction(function () use ($request, $project) {
            $position = (Card::where('project_id', $project->id)->max('position') ?? 0) + 1;

            return $project->cards()
                ->create([...$request->validated(), 'position' => $position])
                ->refresh(); 
        });

        return (new CardResource($card))
            ->additional(['message' => 'Card created'])
            ->response()
            ->setStatusCode(201);
    }

    // PATCH /cards/{card}
    public function update(UpdateCardRequest $request, Card $card): CardResource
    {
        $card->update($request->validated());

        return (new CardResource($card->fresh()))
            ->additional(['message' => 'Card updated']);
    }

    // DELETE /cards/{card}: soft delete, old answers keep their snapshot 
    public function destroy(Card $card): JsonResponse
    {
        DB::transaction(function () use ($card) {
            $projectId = $card->project_id;
            $card->delete();
            $this->renumber($projectId);
        });

        return response()->json(['data' => null, 'message' => 'Card deleted']);
    }

    // PUT /projects/{project}/cards/order  body: { "card_ids": [3, 1, 2] } 
    public function reorder(Request $request, Project $project): JsonResponse|AnonymousResourceCollection
    {
        $request->validate([
            'card_ids' => ['required', 'array', 'min:1'],
            'card_ids.*' => ['integer', 'distinct'],
        ]);

        $sent = collect($request->input('card_ids'))->map(fn ($id) => (int) $id)->sort()->values()->all();
        $current = Card::where('project_id', $project->id)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();

        // The list must contain every card of this project, nothing more, nothing less
        if ($sent !== $current) {
            return $this->fail(
                'The list must contain exactly the cards of this project',
                422,
                ['card_ids' => ['The list must contain exactly the cards of this project']]
            );
        }

        DB::transaction(function () use ($request) {
            foreach ($request->input('card_ids') as $i => $id) {
                Card::where('id', $id)->update(['position' => $i + 1]);
            }
        });

        return CardResource::collection($project->cards()->get())
            ->additional(['message' => 'Order updated']);
    }

    // Keeps positions 1, 2, 3... with no gaps after a delete
    private function renumber(int $projectId): void
    {
        $ids = Card::where('project_id', $projectId)->orderBy('position')->pluck('id');

        foreach ($ids as $i => $id) {
            Card::where('id', $id)->update(['position' => $i + 1]);
        }
    }
}