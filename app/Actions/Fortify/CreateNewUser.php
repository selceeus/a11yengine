<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'agency_name' => ['required', 'string', 'max:255'],
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $agency = Agency::create([
            'name' => $input['agency_name'],
            'slug' => Str::slug($input['agency_name']).'-'.Str::lower(Str::random(6)),
            'billing_email' => $input['email'],
        ]);

        return User::create([
            'agency_id' => $agency->id,
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
    }
}
