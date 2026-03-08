<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user !== null
            && $user->must_change_password
            && ! $request->routeIs('user-password.edit', 'user-password.update', 'logout', 'two-factor.*', 'profile.destroy')
            && ! $request->is('livewire/*')
        ) {
            return redirect()->route('user-password.edit')->with(
                'status',
                'You must set a new password before continuing.'
            );
        }

        return $next($request);
    }
}
