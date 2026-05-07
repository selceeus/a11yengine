<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptInvitationRequest;
use App\Models\AgencyInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class AcceptInvitationController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $invitation = AgencyInvitation::where('token_hash', hash('sha256', $token))->firstOrFail();

        abort_if($invitation->isExpired(), 410, 'This invitation has expired.');
        abort_if($invitation->accepted_at !== null, 410, 'This invitation has already been accepted.');

        return Inertia::render('auth/accept-invitation', [
            'email' => $invitation->email,
            'token' => $token,
        ]);
    }

    public function accept(AcceptInvitationRequest $request, string $token): RedirectResponse
    {
        $invitation = AgencyInvitation::where('token_hash', hash('sha256', $token))->firstOrFail();

        abort_if($invitation->isExpired(), 410, 'This invitation has expired.');
        abort_if($invitation->accepted_at !== null, 410, 'This invitation has already been accepted.');

        $user = User::create([
            'agency_id' => $invitation->agency_id,
            'name' => $request->string('name')->toString(),
            'email' => $invitation->email,
            'password' => Hash::make($request->string('password')->toString()),
        ]);

        $invitation->update(['accepted_at' => now()]);

        Auth::login($user);

        return redirect()->route('dashboard');
    }
}
