<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('agency.{agencyId}', function ($user, $agencyId) {
    return (int) $user->agency_id === (int) $agencyId;
});
