<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Name and optional email. The phone number is the sign-in identity and
     * changes through a separate verified flow.
     *
     * @param  array<string, string|null>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ])->validateWithBag('updateProfileInformation');

        $email = $validated['email'] ?? null;

        $user->forceFill([
            'name' => $validated['name'],
            'email' => $email ? mb_strtolower($email) : null,
            'email_verified_at' => $email === $user->email ? $user->email_verified_at : null,
        ])->save();
    }
}
