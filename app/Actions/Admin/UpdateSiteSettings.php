<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\Palette;
use App\Events\SiteSettingsUpdated;
use App\Models\SiteSetting;
use App\Settings\SiteSettings;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class UpdateSiteSettings
{
    /**
     * @param array{
     *     site_name: string,
     *     support_email: string,
     *     issuer_name: string,
     *     registration_mode: string,
     *     default_theme: string,
     *     allow_plain_export: bool,
     *     require_email_verification?: bool,
     *     site_logo?: ?string,
     *     site_favicon?: ?string,
     *     footer_copyright?: ?string,
     *     footer_tagline?: ?string,
     *     social_github?: ?string,
     *     social_twitter?: ?string,
     *     social_discord?: ?string,
     *     social_bluesky?: ?string,
     *     site_description?: ?string,
     *     session_lifetime?: int,
     *     max_login_attempts?: int
     * } $data
     *
     * @throws ValidationException
     */
    public function handle(array $data): void
    {
        $validator = validator(data: $data, rules: [
            'site_name' => ['required', 'string', 'max:60'],
            'support_email' => ['required', 'email', 'max:255'],
            'issuer_name' => ['required', 'string', 'max:60'],
            'registration_mode' => ['required', 'string', Rule::in(values: ['open', 'first_user', 'closed'])],
            'default_theme' => ['required', 'string', Rule::enum(type: Palette::class)],
            'allow_plain_export' => ['required', 'boolean'],
            'require_email_verification' => ['nullable', 'boolean'],
            'site_logo' => ['nullable', 'string', 'max:500'],
            'site_favicon' => ['nullable', 'string', 'max:500'],
            'footer_copyright' => ['nullable', 'string', 'max:255'],
            'footer_tagline' => ['nullable', 'string', 'max:255'],
            'social_github' => ['nullable', 'string', 'max:255'],
            'social_twitter' => ['nullable', 'string', 'max:255'],
            'social_discord' => ['nullable', 'string', 'max:255'],
            'social_bluesky' => ['nullable', 'string', 'max:255'],
            'site_description' => ['nullable', 'string', 'max:255'],
            'session_lifetime' => ['nullable', 'integer', 'min:5', 'max:10080'],
            'max_login_attempts' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException(validator: $validator);
        }

        SiteSetting::set(key: 'site_name', value: trim(string: $data['site_name']));
        SiteSetting::set(key: 'support_email', value: trim(string: $data['support_email']));
        SiteSetting::set(key: 'issuer_name', value: trim(string: $data['issuer_name']));
        SiteSetting::set(key: 'registration_mode', value: $data['registration_mode']);
        SiteSetting::set(key: 'default_theme', value: $data['default_theme']);
        SiteSetting::set(key: 'allow_plain_export', value: $data['allow_plain_export']);

        if (array_key_exists(key: 'require_email_verification', array: $data)) {
            SiteSetting::set(key: 'require_email_verification', value: (bool) $data['require_email_verification']);
        }

        if (array_key_exists(key: 'site_description', array: $data)) {
            SiteSetting::set(key: 'site_description', value: filled($data['site_description']) ? trim(string: (string) $data['site_description']) : null);
        }

        if (isset($data['session_lifetime'])) {
            SiteSetting::set(key: 'session_lifetime', value: $data['session_lifetime']);
        }

        if (isset($data['max_login_attempts'])) {
            SiteSetting::set(key: 'max_login_attempts', value: $data['max_login_attempts']);
        }

        // Optional branding
        if (array_key_exists(key: 'site_logo', array: $data)) {
            SiteSetting::set(key: 'site_logo', value: filled($data['site_logo']) ? trim(string: (string) $data['site_logo']) : null);
        }

        if (array_key_exists(key: 'site_favicon', array: $data)) {
            SiteSetting::set(key: 'site_favicon', value: filled($data['site_favicon']) ? trim(string: (string) $data['site_favicon']) : null);
        }

        // Optional footer write-up
        if (array_key_exists(key: 'footer_copyright', array: $data)) {
            SiteSetting::set(key: 'footer_copyright', value: filled($data['footer_copyright']) ? trim(string: (string) $data['footer_copyright']) : null);
        }

        if (array_key_exists(key: 'footer_tagline', array: $data)) {
            SiteSetting::set(key: 'footer_tagline', value: filled($data['footer_tagline']) ? trim(string: (string) $data['footer_tagline']) : null);
        }

        // Optional social links
        foreach (['social_github', 'social_twitter', 'social_discord', 'social_bluesky'] as $socialKey) {
            if (array_key_exists(key: $socialKey, array: $data)) {
                SiteSetting::set(key: $socialKey, value: filled($data[$socialKey]) ? trim(string: (string) $data[$socialKey]) : null);
            }
        }

        // Emit domain event for side effects
        SiteSettingsUpdated::dispatch(SiteSettings::current());
    }
}
