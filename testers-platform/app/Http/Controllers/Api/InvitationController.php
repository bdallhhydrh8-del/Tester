<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Services\GrantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->query();

        $query->when($request->query('search'), fn ($q, $search) => $q->where('invitations.name', 'like', "%{$search}%"));

        match ($request->query('state')) {
            'stopped' => $query->whereNotNull('invitations.stopped_at'),
            'expired' => $query->whereNull('invitations.stopped_at')
                ->whereNotNull('invitations.expires_at')
                ->where('invitations.expires_at', '<=', now()),
            'active' => $query->whereNull('invitations.stopped_at')
                ->where(fn ($q) => $q->whereNull('invitations.expires_at')->orWhere('invitations.expires_at', '>', now())),
            default => null,
        };

        $links = $query->latest('invitations.id')
            ->paginate($this->perPage($request))
            ->through(fn (Invitation $invitation) => $this->present($invitation));

        return response()->json($links);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'project_ids' => 'required|array|min:1',
            'project_ids.*' => 'integer|distinct|exists:projects,id',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $invitation = DB::transaction(function () use ($data, $request) {
            $invitation = Invitation::create([
                'name' => trim($data['name']),
                'token' => Str::random(40),
                'expires_at' => $data['expires_at'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            DB::table('invitation_project')->insert(
                collect($data['project_ids'])
                    ->map(fn ($projectId) => ['invitation_id' => $invitation->id, 'project_id' => $projectId])
                    ->all()
            );

            return $invitation;
        });

        return response()->json([
            'data' => $this->present($this->query()->findOrFail($invitation->id)),
            'message' => 'Invitation link created.',
        ], 201);
    }

    public function show(int $id)
    {
        $invitation = $this->query()->findOrFail($id);

        return response()->json(['data' => $this->detail($invitation)]);
    }

    public function update(Request $request, int $id)
    {
        $invitation = Invitation::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'expires_at' => 'sometimes|nullable|date|after:now',
        ]);

        if (array_key_exists('name', $data)) {
            $data['name'] = trim($data['name']);
        }

        $invitation->update($data);

        return response()->json([
            'data' => $this->detail($this->query()->findOrFail($id)),
            'message' => 'Invitation link updated.',
        ]);
    }

    /** Blocks new registrations and logins through the link. Safe to call twice. */
    public function stop(int $id)
    {
        $invitation = Invitation::findOrFail($id);

        if ($invitation->stopped_at === null) {
            $invitation->update(['stopped_at' => now()]);
        }

        return response()->json([
            'data' => $this->present($this->query()->findOrFail($id)),
            'message' => 'Invitation link stopped.',
        ]);
    }

    /** FR-I7: testers who already joined the link gain access to the new projects. */
    public function addProjects(Request $request, int $id, GrantService $grants)
    {
        $invitation = Invitation::findOrFail($id);

        $data = $request->validate([
            'project_ids' => 'required|array|min:1',
            'project_ids.*' => 'integer|distinct|exists:projects,id',
        ]);

        DB::transaction(function () use ($invitation, $data, $grants) {
            DB::table('invitation_project')->insertOrIgnore(
                collect($data['project_ids'])
                    ->map(fn ($projectId) => ['invitation_id' => $invitation->id, 'project_id' => $projectId])
                    ->all()
            );

            $grants->grantProjectsToJoinedTesters($invitation, $data['project_ids']);
        });

        return response()->json([
            'data' => $this->detail($this->query()->findOrFail($id)),
            'message' => 'Projects added to the link.',
        ]);
    }

    /** Invitations with their project and joined-tester counts. */
    private function query()
    {
        return Invitation::query()
            ->select('invitations.*')
            ->selectSub(
                DB::table('invitation_testers')->selectRaw('count(*)')
                    ->whereColumn('invitation_testers.invitation_id', 'invitations.id'),
                'joined_count'
            )
            ->selectSub(
                DB::table('invitation_project')->selectRaw('count(*)')
                    ->whereColumn('invitation_project.invitation_id', 'invitations.id'),
                'projects_count'
            );
    }

    private function present(Invitation $invitation): array
    {
        $url = rtrim(config('tester.frontend_url'), '/') . '/join/' . $invitation->token;

        return [
            'id' => $invitation->id,
            'name' => $invitation->name,
            'token' => $invitation->token,
            'state' => $invitation->state(),
            'expires_at' => $invitation->expires_at,
            'stopped_at' => $invitation->stopped_at,
            'created_at' => $invitation->created_at,
            'joined_count' => (int) $invitation->joined_count,
            'projects_count' => (int) $invitation->projects_count,
            'share_url' => $url,
            'whatsapp_url' => 'https://wa.me/?text=' . rawurlencode($invitation->name . ': ' . $url),
        ];
    }

    /** The link, its projects, and the testers who joined (by number only, no names or phones). */
    private function detail(Invitation $invitation): array
    {
        $projects = DB::table('invitation_project')
            ->join('projects', 'projects.id', '=', 'invitation_project.project_id')
            ->where('invitation_project.invitation_id', $invitation->id)
            ->orderBy('projects.name')
            ->select('projects.id', 'projects.name', 'projects.type', 'projects.status')
            ->get();

        $testers = DB::table('invitation_testers')
            ->join('users', 'users.id', '=', 'invitation_testers.tester_id')
            ->where('invitation_testers.invitation_id', $invitation->id)
            ->orderBy('users.tester_number')
            ->select('users.id', 'users.tester_number', 'invitation_testers.joined_at')
            ->get();

        return $this->present($invitation) + ['projects' => $projects, 'testers' => $testers];
    }

    private function perPage(Request $request): int
    {
        $number = $request->integer('per_page', 20);

        return $number < 1 ? 20 : min($number, 100);
    }
}