<?php

/*
|--------------------------------------------------------------------------
| Site provisioning (install-time only)
|--------------------------------------------------------------------------
|
| Read by `php artisan site:install` — the non-interactive equivalent of
| the web install wizard (routes/install.php, InstallController), for
| scripted clone provisioning. Not used after install; safe to leave
| blank in .env once a site is up and running.
|
*/

return [
    'admin_name' => env('SITE_ADMIN_NAME', 'Admin'),
    'admin_email' => env('SITE_ADMIN_EMAIL'),
    'admin_password' => env('SITE_ADMIN_PASSWORD'),
    'currency' => env('SITE_DEFAULT_CURRENCY_CODE'),
];
