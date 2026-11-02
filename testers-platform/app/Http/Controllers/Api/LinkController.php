<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use App\Services\GrantService;
use App\Services\TesterLogin;
use App\Support\PhoneNormalizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LinkController extends Controller
{
    /** GET /links/{token}: active, expired, stopped or unknown */
    public function show(string $token)
    {
        $invitation = Invitation::where('token', $token)->first();

        if (! $invitation) {
            return response()->json(['data' => ['state' => 'unknown']]);
        }

        $state = $invitation->state();

        if ($state !== 'active') {
            return response()->json(['data' => ['state' => $state]]);
        }

        $count = $invitation->projectIds()->count();

        return response()->json(['data' => [
            'state' => 'active',
            'name' => $invitation->name,
            'projects_count' => $count,
            'has_single_project' => $count === 1,
        ]]);
    }

    /** POST /links/{token}/register: name and phone */
    public function register(Request $request, string $token, GrantService $grants, TesterLogin $login)
    {
        $invitation = $this->usableInvitation($token);
        if ($invitation instanceof JsonResponse) {
            return $invitation;
        }

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'required|string|max:30',
        ]);

        $phone = PhoneNormalizer::normalize($data['phone']);

        if ($phone === null) {
            return response()->json([
                'message' => 'Enter a valid Yemeni mobile number.',
                'code' => 'invalid_phone',
                'errors' => ['phone' => ['Enter a valid Yemeni mobile number.']],
            ], 422);
        }

        if (User::where('phone', $phone)->exists()) {
            return $this->phoneTaken();
        }

        try {
            $tester = DB::transaction(function () use ($data, $phone, $invitation, $grants) {
                $tester = User::create([
                    'role' => Role::Tester,
                    'status' => 'active',
                    'name' => trim($data['name']),
                    'phone' => $phone,
                    'tester_number' => $this->nextTesterNumber(),
                ]);

                $grants->grant($tester, $invitation);

                return $tester;
            });
        } catch (UniqueConstraintViolationException) {
            // Two requests registered the same phone at the same moment
            return $this->phoneTaken();
        }

        $login->startSession($request, $tester);

        return response()->json([
            'data' => ['user' => $tester, 'next' => $this->next($invitation)],
            'message' => 'Registered.',
        ], 201);
    }

    /** POST /links/{token}/login: phone only */
    public function login(Request $request, string $token, GrantService $grants, TesterLogin $login)
    {
        $invitation = $this->usableInvitation($token);
        if ($invitation instanceof JsonResponse) {
            return $invitation;
        }

        $data = $request->validate([
            'phone' => 'required|string|max:30',
        ]);

        $tester = $login->find($request, $data['phone']);
        $grants->grant($tester, $invitation);
        $login->startSession($request, $tester);

        return response()->json([
            'data' => ['user' => $tester, 'next' => $this->next($invitation)],
            'message' => 'Logged in.',
        ]);
    }

    /** Returns the invitation, or a JSON error if the link cannot be used. */
    private function usableInvitation(string $token): Invitation|JsonResponse
    {
        $invitation = Invitation::where('token', $token)->first();

        if (! $invitation) {
            return response()->json(['message' => 'This link does not exist.', 'code' => 'link_unknown'], 404);
        }

        return match ($invitation->state()) {
            'stopped' => response()->json(['message' => 'This link has been stopped.', 'code' => 'link_stopped'], 409),
            'expired' => response()->json(['message' => 'This link has expired.', 'code' => 'link_expired'], 409),
            default => $invitation,
        };
    }

    private function phoneTaken(): JsonResponse
    {
        return response()->json([
            'message' => 'This number is already registered. Please log in with your phone number.',
            'code' => 'phone_registered',
        ], 409);
    }

    /** One project: go straight to it. Otherwise: the home page. */
    private function next(Invitation $invitation): array
    {
        $ids = $invitation->projectIds();

        return $ids->count() === 1
            ? ['to' => 'project', 'project_id' => $ids->first()]
            : ['to' => 'home'];
    }

    /** Tester numbers are handed out once and never reused. */
    private function nextTesterNumber(): int
    {
        $row = DB::table('tester_counter')->lockForUpdate()->first();

        if (! $row) {
            $id = DB::table('tester_counter')->insertGetId(['last_number' => 0]);
            $row = DB::table('tester_counter')->where('id', $id)->lockForUpdate()->first();
        }

        $next = $row->last_number + 1;
        DB::table('tester_counter')->where('id', $row->id)->update(['last_number' => $next]);

        return $next;
    }
}