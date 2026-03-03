<?php

namespace App\Domain\Agency;

use App\Models\Agency;
use App\Models\AgencyInvitation;
use App\Models\User;
use App\Notifications\AgencyInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteUser
{
    /**
     * @throws ValidationException
     */
    public function handle(Agency $agency, string $email): AgencyInvitation
    {
        $this->assertNoExistingMember($agency, $email);
        $this->assertNoPendingInvitation($agency, $email);

        $invitation = AgencyInvitation::create([
            'agency_id' => $agency->id,
            'email' => $email,
            'token' => Str::random(64),
        ]);

        Notification::route('mail', $email)->notify(new AgencyInvitationNotification($invitation));

        return $invitation;
    }

    private function assertNoExistingMember(Agency $agency, string $email): void
    {
        $exists = User::withoutGlobalScopes()
            ->where('agency_id', $agency->id)
            ->where('email', $email)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'email' => ['This user is already a member of this agency.'],
            ]);
        }
    }

    private function assertNoPendingInvitation(Agency $agency, string $email): void
    {
        $exists = AgencyInvitation::where('agency_id', $agency->id)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->where('created_at', '>=', now()->subDays(7))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'email' => ['A pending invitation already exists for this email address.'],
            ]);
        }
    }
}
