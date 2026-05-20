<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => $this->passwordRules(),
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'username' => $this->generateUsername($input['email']),
        ]);
    }

    protected function generateUsername(string $email): string
    {
        $base = Str::slug(Str::before($email, '@'), '');
        $base = $base !== '' ? $base : 'user';

        $candidate = $base;
        $i = 1;

        while (User::where('username', $candidate)->exists()) {
            $candidate = $base.$i++;
        }

        return $candidate;
    }
}
