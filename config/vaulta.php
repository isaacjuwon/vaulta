<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Open Registration
    |--------------------------------------------------------------------------
    |
    | Controls whether public account registration is open (`true`), closed
    | (`false`), or limited to the initial setup account (`first_user`).
    |
    */
    'registration' => env('VAULTA_REGISTRATION', true),

    /*
    |--------------------------------------------------------------------------
    | Issuer Label
    |--------------------------------------------------------------------------
    |
    | Default issuer label embedded in generated 2FA provisioning URIs.
    |
    */
    'issuer' => env('VAULTA_ISSUER', 'Vaulta'),

    /*
    |--------------------------------------------------------------------------
    | Email Verification Requirement
    |--------------------------------------------------------------------------
    |
    | When true, users must verify their email address before accessing vaults.
    |
    */
    'require_email_verification' => env('VAULTA_REQUIRE_EMAIL_VERIFICATION', true),
];
