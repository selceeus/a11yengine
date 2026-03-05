<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\AgencyInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
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

        return Inertia::render('team/index', [
            'members' => $members,
            'invitations' => $invitations,
        ]);
    }

    public function destroyMember(User $user): RedirectResponse
    {
        abort_if($user->id === Auth::id(), 422, 'You cannot remove yourself from the team.');
        abort_if($user->agency_id !== $this->agency->id, 403);

        $user->delete();

        return redirect()->route('team.index')->with('status', 'Team member removed.');
    }

    public function destroyInvitation(AgencyInvitation $invitation): RedirectResponse
    {
        abort_if($invitation->agency_id !== $this->agency->id, 403);

        $invitation->delete();

        return redirect()->route('team.index')->with('status', 'Invitation cancelled.');
    }
}
