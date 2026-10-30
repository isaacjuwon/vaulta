<?php

declare(strict_types=1);

namespace App\Actions\Contact;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final readonly class SubmitContactMessage
{
    /**
     * @param  array{name: string, email: string, subject: string, message: string}  $data
     *
     * @throws ValidationException
     */
    public function handle(array $data, ?User $user = null, ?string $ipAddress = null): ContactMessage
    {
        $validator = validator(data: $data, rules: [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException(validator: $validator);
        }

        return ContactMessage::query()->create(attributes: [
            'user_id' => $user?->id,
            'name' => trim(string: $data['name']),
            'email' => trim(string: $data['email']),
            'subject' => trim(string: $data['subject']),
            'message' => trim(string: $data['message']),
            'ip_address' => $ipAddress,
        ]);
    }
}
