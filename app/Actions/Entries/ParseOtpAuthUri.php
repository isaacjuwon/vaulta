<?php

declare(strict_types=1);

namespace App\Actions\Entries;

use App\Support\Totp\OtpAuthPayload;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class ParseOtpAuthUri
{
    /**
     * @throws ValidationException
     */
    public function handle(string $rawUri): OtpAuthPayload
    {
        try {
            return OtpAuthPayload::fromUri(rawUri: $rawUri);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(messages: [
                'setupUri' => $exception->getMessage(),
            ]);
        }
    }
}
