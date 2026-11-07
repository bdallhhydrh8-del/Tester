<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $admins = User::where('role', Role::Admin->value)
            ->select(['id', 'name', 'username', 'status', 'last_login_at', 'created_at'])
            ->when($search !== '', fn ($query) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
            ))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return response()->json($admins);
    }

    public function show(int $id)
    {
        return new AdminResource($this->findAdmin($id));

    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'username' => 'required|string|alpha_dash|max:50|unique:users,username',
            'password' => array_merge(['required'], $this->passwordRules()),
        ]);

        $admin = User::create([
            'role' => Role::Admin,
            'status' => 'active',
            'name' => trim($data['name']),
            'username' => $data['username'],
            'password' => $data['password'],
        ]);

        return (new AdminResource($admin))
            ->additional(['message' => 'Admin created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, int $id)
    {
        $admin = $this->findAdmin($id);

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'username' => ['sometimes', 'required', 'string', 'alpha_dash', 'max:50',
                Rule::unique('users', 'username')->ignore($admin->id)],
            'password' => array_merge(['sometimes', 'required'], $this->passwordRules()),
        ]);

        $admin->update($data);

        // A new password must log the admin out everywhere
        if (array_key_exists('password', $data)) {
            $this->endSessions($admin);
        }

        return (new AdminResource($admin->fresh()))
            ->additional(['message' => 'Admin updated.']);
    }

    public function deactivate(int $id)
    {
        $admin = $this->findAdmin($id);
        $admin->update(['status' => 'deactivated']);
        $this->endSessions($admin);

        return (new AdminResource($admin->fresh()))
            ->additional(['message' => 'Admin deactivated.']);
    }

    public function activate(int $id)
    {
        $admin = $this->findAdmin($id);
        $admin->update(['status' => 'active']);

        return (new AdminResource($admin->fresh()))
            ->additional(['message' => 'Admin activated.']);
    }

    public function destroy(int $id)
    {
        $admin = $this->findAdmin($id);
        $this->endSessions($admin);
        $admin->delete(); // soft delete: projects and links created by this admin stay

        return response()->json(['message' => 'Admin deleted.']);
    }

    /** FR-A11: at least 8 characters with letters and numbers, and a matching password_confirmation. */
    private function passwordRules(): array
    {
        return ['string', 'confirmed', 'max:100', Password::min(8)->letters()->numbers()];
    }

    /**
     * Only users with the plain admin role. The SuperAdmin is never found here,
     * so it cannot be edited, deactivated or deleted from the interface.
     */
    private function findAdmin(int $id): User
    {
        return User::where('role', Role::Admin->value)->findOrFail($id);
    }

    private function endSessions(User $admin): void
    {
        DB::table('sessions')->where('user_id', $admin->id)->delete();
    }

    private function perPage(Request $request): int
    {
        $number = $request->integer('per_page', 20);

        return $number < 1 ? 20 : min($number, 100);
    }
}