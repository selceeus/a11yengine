<?php

use App\Domain\Agency\InviteUser;
use App\Models\Agency;
use App\Models\AgencyInvitation;
use App\Models\User;
use App\Notifications\AgencyInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->service = app(InviteUser::class);
    Notification::fake();
});

it('creates an invitation record', function (): void {
    $this->service->handle($this->agency, 'invitee@example.com');

    $this->assertDatabaseHas('agency_invitations', [
        'agency_id' => $this->agency->id,
        'email' => 'invitee@example.com',
        'accepted_at' => null,
    ]);
});

it('returns the created invitation', function (): void {
    $invitation = $this->service->handle($this->agency, 'invitee@example.com');

    expect($invitation)->toBeInstanceOf(AgencyInvitation::class)
        ->and($invitation->email)->toBe('invitee@example.com')
        ->and($invitation->token)->toHaveLength(64);
});

it('sends the notification email', function (): void {
    $this->service->handle($this->agency, 'invitee@example.com');

    Notification::assertSentOnDemand(
        AgencyInvitationNotification::class,
        function ($notification, $channels, $notifiable): bool {
            $mailRoute = $notifiable->routes['mail'];
            $addresses = is_array($mailRoute) ? $mailRoute : [$mailRoute];

            return in_array('invitee@example.com', $addresses);
        }
    );
});

it('rejects inviting an existing member', function (): void {
    User::factory()->create([
        'agency_id' => $this->agency->id,
        'email' => 'member@example.com',
    ]);

    expect(fn () => $this->service->handle($this->agency, 'member@example.com'))
        ->toThrow(ValidationException::class);
});

it('rejects a duplicate pending invitation', function (): void {
    AgencyInvitation::factory()->create([
        'agency_id' => $this->agency->id,
        'email' => 'invitee@example.com',
    ]);

    expect(fn () => $this->service->handle($this->agency, 'invitee@example.com'))
        ->toThrow(ValidationException::class);
});

it('allows reinviting after a previous invitation was accepted', function (): void {
    AgencyInvitation::factory()->accepted()->create([
        'agency_id' => $this->agency->id,
        'email' => 'invitee@example.com',
    ]);

    $invitation = $this->service->handle($this->agency, 'invitee@example.com');

    expect($invitation)->toBeInstanceOf(AgencyInvitation::class);
});
