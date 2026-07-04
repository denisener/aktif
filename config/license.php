<?php

/*
|--------------------------------------------------------------------------
| LicenseBox integration (self-hosted, license.fikirmeclisi.com)
|--------------------------------------------------------------------------
|
| One LicenseBox "Product" represents this whole base template; every
| cloned site is a separate "activation" of that same product, identified
| by its own license_code + client_name (see app/Services/LicenseService.php).
|
| product_id/api_url are constant across every clone (same LicenseBox
| product), so they default here. api_key, code, and client_name are
| per-deployment and must be set in .env — left blank on purpose, same
| convention as every other API credential in this project (Stripe,
| Iyzico, etc. in .env.example).
|
*/

return [
    'api_url' => env('LICENSE_API_URL', 'https://license.fikirmeclisi.com/'),
    'product_id' => env('LICENSE_PRODUCT_ID', 'BF5645A8'),
    'api_key' => env('LICENSE_API_KEY'),
    'code' => env('LICENSE_CODE'),
    'client_name' => env('LICENSE_CLIENT_NAME'),
    'verify_period_days' => env('LICENSE_VERIFY_PERIOD_DAYS', 3),
];
