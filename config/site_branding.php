<?php

/*
|--------------------------------------------------------------------------
| Site branding (per-clone)
|--------------------------------------------------------------------------
|
| These values differentiate one cloned site from another. They get
| written into the business_settings table (the same table the admin
| panel edits) by the `php artisan site:brand` command, so provisioning
| a new site is "set these env vars, run one command" instead of
| clicking through the admin UI by hand.
|
| The 'theme' key must match a folder under resources/views/frontend/
| (built-in: classic, megamart, metro, minima, nexa, thecore, reclassic,
| or a custom theme folder cloned from one of those).
|
*/

return [
    'theme' => env('SITE_THEME', 'classic'),

    'site_name' => env('SITE_NAME'),
    'website_name' => env('SITE_NAME'),

    'header_logo' => env('SITE_LOGO_HEADER'),
    'footer_logo' => env('SITE_LOGO_FOOTER'),
    'system_logo_black' => env('SITE_LOGO_BLACK'),
    'system_logo_white' => env('SITE_LOGO_WHITE'),

    'base_color' => env('SITE_PRIMARY_COLOR'),
    'secondary_base_color' => env('SITE_SECONDARY_COLOR'),

    'contact_email' => env('SITE_CONTACT_EMAIL'),
    'contact_phone' => env('SITE_CONTACT_PHONE'),
    'contact_address' => env('SITE_CONTACT_ADDRESS'),
    'facebook_link' => env('SITE_FACEBOOK_LINK'),

    'meta_title' => env('SITE_META_TITLE'),
    'meta_description' => env('SITE_META_DESCRIPTION'),
    'meta_keywords' => env('SITE_META_KEYWORDS'),
];
