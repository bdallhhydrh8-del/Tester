<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return response()->json(['data' => $this->present($request->user())]);
    }

    /** FR-A14: an admin edits their own full name and password. */
    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'password' => ['sometimes', 'required', 'string', 'confirmed', 'max:100',
                Password::min(8)->letters()->numbers()],
            'current_password' => 'required_with:password|string',
        ]);

        if (array_key_exists('password', $data)) {
            // FR-A7: the SuperAdmin password is changed by the developer in the database
            if ($user->role === Role::SuperAdmin) {
                return response()->json([
                    'message' => 'The SuperAdmin password cannot be changed from the interface.',
                    'code' => 'superadmin_password_locked',
                ], 403);
            }

            if (! Hash::check($data['current_password'], $user->password)) {
                return response()->json([
                    'message' => 'The current password is wrong.',
                    'code' => 'wrong_password',
                    'errors' => ['current_password' => ['The current password is wrong.']],
                ], 422);
            }
        }

        $changes = [];

        if (array_key_exists('name', $data)) {
            $changes['name'] = trim($data['name']);
        }

        if (array_key_exists('password', $data)) {
            $changes['password'] = $data['password'];
        }

        if ($changes !== []) {
            $user->update($changes);
        }

        if (isset($changes['password'])) {
            $this->endOtherSessions($request, $user);
        }

        return response()->json(['data' => $this->present($user->fresh()), 'message' => 'Profile updated.']);
    }

    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'role' => $user->role->value,
            'created_at' => $user->created_at,
            'projects_created' => $this->projectsCreated($user),
        ];
    }

    /** FR-D6. Dev 2 owns the projects table: remove the checks once it is merged. */
    private function projectsCreated(User $user): int
    {
        if (! Schema::hasTable('projects') || ! Schema::hasColumn('projects', 'created_by')) {
            return 0;
        }

        return DB::table('projects')->where('created_by', $user->id)->count();
    }

    /** After a password change every other device must log in again. */
    private function endOtherSessions(Request $request, User $user): void
    {
        $currentId = $request->hasSession() ? $request->session()->getId() : null;

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->when($currentId, fn ($query) => $query->where('id', '!=', $currentId))
            ->delete();
    }
}