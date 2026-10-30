<?php

declare(strict_types=1);

namespace App\Events;

use App\Settings\SiteSettings;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SiteSettingsUpdated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public SiteSettings $settings,
    ) {}
}
