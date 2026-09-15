<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $site_name;

    public string $site_description;

    public string $contact_email;

    public string $contact_phone;

    public string $facebook_link;

    public string $zalo_link;

    public static function group(): string
    {
        return 'general';
    }
}
