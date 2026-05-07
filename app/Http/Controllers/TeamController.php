<?php

namespace App\Http\Controllers;

use App\Domain\Agency\CreateTeamMember;
use App\Enums\UserRole as UserRoleEnum;
use App\Http\Requests\StoreTeamMemberRequest;
use App\Http\Requests\UpdateTeamMemberPasswordRequest;
use App\Http\Requests\UpdateTeamMemberRequest;
use App\Http\Requests\UpdateTeamMemberRoleRequest;
use App\Models\Agency;
use App\Models\AgencyInvitation;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function __construct(private readonly Agency $agency) {}

    public function index(): Response
    {
        $members = $this->agency->users()
            ->select(['id', 'name', 'email', 'created_at'])
            ->orderBy('name')
            ->get();

        $invitations = $this->agency->invitations()
            ->whereNull('accepted_at')
            ->where('created_at', '>=', now()->subDays(7))
            ->select(['id', 'email', 'created_at'])
            ->orderByDesc('created_at')
            ->get();

        $canManageTeam = Auth::user()->canManageAgency($this->agency->id);

        return Inertia::render('team/index', [
            'members' => $members,
            'invitations' => $invitations,
            'canManageTeam' => $canManageTeam,
        ]);
    }

    public function create(): Response
    {
        abort_unless(Auth::user()->canManageAgency($this->agency->id), 403);

        return Inertia::render('team/create', [
            'availableRoles' => $this->availableRoles(),
        ]);
    }

    public function store(StoreTeamMemberRequest $request, CreateTeamMember $createTeamMember): RedirectResponse
    {
        abort_unless(Auth::user()->canManageAgency($this->agency->id), 403);

        $role = $request->filled('role') ? UserRoleEnum::from($request->string('role')->toString()) : null;

        $createTeamMember->handle(
            $this->agency,
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $role,
        );

        return redirect()->route('team.index')->with('status', 'Team member added.');
    }

    public function edit(User $user): Response
    {
        abort_unless(Auth::user()->canManageAgency($this->agency->id), 403);
        abort_if($user->agency_id !== $this->agency->id, 403);

        $agencyRole = UserRole::where('user_id', $user->id)
            ->where('agency_id', $this->agency->id)
            ->whereNull('organization_id')
            ->whereNull('property_id')
            ->value('role');

        return Inertia::render('team/edit', [
            'member' => $user->only('id', 'name', 'email', 'must_change_password'),
            'currentRole' => $agencyRole?->value,
            'availableRoles' => $this->availableRoles(),
        ]);
    }

    public function update(UpdateTeamMemberRequest $request, User $user): RedirectResponse
    {
        abort_unless(Auth::user()->canManageAgency($this->agency->id), 403);
        abort_if($user->agency_id !== $this->agency->id, 403);

        $user->update($request->only('name', 'email'));

        return redirect()->route('team.members.edit', $user)->with('status', 'Profile updated.');
    }

    public function updatePassword(UpdateTeamMemberPasswordRequest $request, User $user): RedirectResponse
    {
        abort_unless(Auth::user()->canManageAgency($this->agency->id), 403);
        abort_if($user->agency_id !== $this->agency->id, 403);

        $user->update([
            'password' => Hash::make($request->string('password')->toString()),
            'must_change_password' => true,
        ]);

        return redirect()->route('team.members.edit', $user)->with('status', 'Password updated. The user will be asked to change it on next login.');
    }

    public function updateRole(UpdateTeamMemberRoleRequest $request, User $user): RedirectResponse
    {
        abort_unless(Auth::user()->canManageAgency($this->agency->id), 403);
        abort_if($user->agency_id !== $this->agency->id, 403);

        UserRole::where('user_id', $user->id)
            ->where('agency_id', $this->agency->id)
            ->whereNull('organization_id')
            ->whereNull('property_id')
            ->delete();

        if ($request->filled('role')) {
            $user->roles()->create([
                'role' => UserRoleEnum::from($request->string('role')->toString()),
                'agency_id' => $this->agency->id,
            ]);
        }

        return redirect()->route('team.members.edit', $user)->with('status', 'Role updated.');
    }

    public function destroyMember(User $user): RedirectResponse
    {
        abort_unless(Auth::user()->canManageAgency($this->agency->id), 403);
        abort_if($user->id === Auth::id(), 422, 'You cannot remove yourself from the team.');
        abort_if($user->agency_id !== $this->agency->id, 403);

        $user->delete();

        return redirect()->route('team.index')->with('status', 'Team member removed.');
    }

    public function destroyInvitation(AgencyInvitation $invitation): RedirectResponse
    {
        abort_unless(Auth::user()->canManageAgency($this->agency->id), 403);
        abort_if($invitation->agency_id !== $this->agency->id, 403);

        $invitation->delete();

        return redirect()->route('team.index')->with('status', 'Invitation cancelled.');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function availableRoles(): array
    {
        return [
            ['value' => UserRoleEnum::AgencyAdmin->value, 'label' => 'Agency Admin'],
            ['value' => UserRoleEnum::Editor->value, 'label' => 'Editor'],
            ['value' => UserRoleEnum::Viewer->value, 'label' => 'Viewer'],
        ];
    }
}
