<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TesterController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $testers = User::where('role', Role::Tester->value)
            ->select(['id', 'name', 'phone', 'tester_number', 'last_login_at', 'created_at'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");

                    if (ctype_digit($search)) {
                        $q->orWhere('tester_number', (int) $search);
                    }
                });
            })
            ->orderBy('tester_number')
            ->paginate($this->perPage($request));

        return response()->json($testers);
    }

    public function show(int $id)
    {
        $tester = $this->findTester($id);

        $invitations = DB::table('invitation_testers')
            ->join('invitations', 'invitations.id', '=', 'invitation_testers.invitation_id')
            ->where('invitation_testers.tester_id', $tester->id)
            ->select('invitations.id', 'invitations.name', 'invitation_testers.joined_at')
            ->get();

        // Dev 3 owns project_accesses. Remove this guard once the table is merged.
        $accesses = Schema::hasTable('project_accesses')
            ? DB::table('project_accesses')
                ->where('tester_id', $tester->id)
                ->select('project_id', 'status', 'started_at', 'finished_at')
                ->get()
            : [];

        return response()->json(['data' => [
            'id' => $tester->id,
            'name' => $tester->name,
            'phone' => $tester->phone,
            'tester_number' => $tester->tester_number,
            'last_login_at' => $tester->last_login_at,
            'created_at' => $tester->created_at,
            'invitations' => $invitations,
            'accesses' => $accesses,
        ]]);
    }

    public function update(Request $request, int $id)
    {
        $tester = $this->findTester($id);

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'phone' => 'sometimes|required|string|max:30',
        ]);

        if (array_key_exists('name', $data)) {
            $data['name'] = trim($data['name']);
        }

        if (array_key_exists('phone', $data)) {
            $phone = PhoneNormalizer::normalize($data['phone']);

            if ($phone === null) {
                return response()->json([
                    'message' => 'Enter a valid Yemeni mobile number.',
                    'code' => 'invalid_phone',
                    'errors' => ['phone' => ['Enter a valid Yemeni mobile number.']],
                ], 422);
            }

            if (User::where('phone', $phone)->where('id', '!=', $tester->id)->exists()) {
                return response()->json([
                    'message' => 'This number belongs to another tester.',
                    'code' => 'phone_registered',
                ], 409);
            }

            $data['phone'] = $phone;
        }

        $tester->update($data);

        return response()->json(['data' => $tester->fresh(), 'message' => 'Tester updated.']);
    }

    private function findTester(int $id): User
    {
        return User::where('role', Role::Tester->value)->findOrFail($id);
    }

    private function perPage(Request $request): int
    {
        $number = $request->integer('per_page', 20);

        return $number < 1 ? 20 : min($number, 100);
    }
}