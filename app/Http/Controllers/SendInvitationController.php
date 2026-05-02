<?php

namespace App\Http\Controllers;

use App\Domain\Agency\InviteUser;
use App\Http\Requests\SendInvitationRequest;
use App\Models\Agency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class SendInvitationController extends Controller
{
    public function __construct(private readonly InviteUser $inviteUser) {}

    public function __invoke(SendInvitationRequest $request, Agency $agency): RedirectResponse
    {
        abort_unless(Auth::user()->canManageAgency($agency->id), 403);

        $this->inviteUser->handle($agency, $request->string('email')->toString());

        return redirect()->back()->with('status', 'Invitation sent.');
    }
}
