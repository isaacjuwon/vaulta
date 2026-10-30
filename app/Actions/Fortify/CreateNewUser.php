<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Actions\Auth\RegisterUser;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Support\Registration;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Override;

final readonly class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;
    use ProfileValidationRules;

    public function __construct(
        private RegisterUser $registerUser = new RegisterUser,
    ) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    #[Override]
    public function create(array $input): User
    {
        if (! Registration::open()) {
            throw ValidationException::withMessages(messages: [
                'email' => 'Public registration is closed.',
            ]);
        }

        Validator::make(
            data: $input,
            rules: [
                ...$this->profileRules(),
                'password' => $this->passwordRules(),
            ],
        )->validate();

        return $this->registerUser->handle(
            name: $input['name'],
            email: $input['email'],
            password: $input['password'],
        );
    }
}
