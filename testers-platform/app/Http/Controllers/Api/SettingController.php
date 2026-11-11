<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDefaultCardsRequest;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    // GET /settings/default-cards 
    public function showDefaultCards(): JsonResponse
    {
        return response()->json([
            'data' => ['default_cards' => Setting::current()->default_cards],
            'message' => 'ok',
        ]);
    }

    // PUT /settings/default-cards: replaces the default form. New projects only. 
    public function updateDefaultCards(UpdateDefaultCardsRequest $request): JsonResponse
    {
        // Store every field, so the project "create" step always finds the same shape
        $cards = collect($request->validated('default_cards'))
            ->map(fn (array $card) => [
                'title' => $card['title'],
                'instructions' => $card['instructions'],
                'ask_ease' => $card['ask_ease'] ?? true,
                'ask_notes' => $card['ask_notes'] ?? true,
                'ask_file' => $card['ask_file'] ?? false,
                'required' => $card['required'] ?? true,
                'custom_questions' => $card['custom_questions'] ?? null,
            ])
            ->values()
            ->all();

        $setting = Setting::current();
        $setting->update(['default_cards' => $cards]);

        return response()->json([
            'data' => ['default_cards' => $setting->fresh()->default_cards],
            'message' => 'Default task form updated',
        ]);
    }

    // GET /settings/default-note
    public function showDefaultNote(): JsonResponse
    {
        return response()->json([
            'data' => ['default_note' => Setting::current()->default_note],
            'message' => 'ok',
        ]);
    }

    // PUT /settings/default-note  body: { "default_note": "..." }
    // Changes the note for new projects only. Existing projects keep their own note.
    public function updateDefaultNote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'default_note' => ['required', 'string', 'max:2000'],
        ]);

        $setting = Setting::current();
        $setting->update($data);

        return response()->json([
            'data' => ['default_note' => $setting->fresh()->default_note],
            'message' => 'Default note updated',
        ]);
    }
}