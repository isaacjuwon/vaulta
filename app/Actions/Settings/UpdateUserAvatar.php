<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\User;
use Illuminate\Validation\ValidationException;

final readonly class UpdateUserAvatar
{
    private const int MAX_DATA_URL_LENGTH = 1_400_000;

    private const int MAX_DECODED_BYTES = 1_000_000;

    private const int MAX_DIMENSION = 1024;

    /**
     * @throws ValidationException
     */
    public function handle(User $user, string $dataUrl): User
    {
        $validated = $this->validateAndNormalize(dataUrl: $dataUrl);

        if ($validated === null) {
            throw ValidationException::withMessages(messages: [
                'avatarDataUrl' => 'Choose a valid PNG, JPEG, or WebP image smaller than 1 MB.',
            ]);
        }

        $user->forceFill(attributes: [
            'avatar' => $validated,
        ])->save();

        return $user;
    }

    private function validateAndNormalize(string $dataUrl): ?string
    {
        if (strlen(string: $dataUrl) > self::MAX_DATA_URL_LENGTH) {
            return null;
        }

        $matches = [];
        if (preg_match(pattern: '#^data:image/(png|jpeg|webp);base64,([A-Za-z0-9+/]+={0,2})$#', subject: $dataUrl, matches: $matches) !== 1) {
            return null;
        }

        $decoded = base64_decode(string: $matches[2], strict: true);
        if ($decoded === false || strlen(string: $decoded) > self::MAX_DECODED_BYTES) {
            return null;
        }

        $details = @getimagesizefromstring(string: $decoded);
        if (! is_array($details)) {
            return null;
        }

        $mime = $details['mime'];
        $width = $details[0];
        $height = $details[1];

        if (
            ! in_array(needle: $mime, haystack: ['image/png', 'image/jpeg', 'image/webp'], strict: true)
            || $width < 1
            || $height < 1
            || $width > self::MAX_DIMENSION
            || $height > self::MAX_DIMENSION
        ) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode(string: $decoded);
    }
}
