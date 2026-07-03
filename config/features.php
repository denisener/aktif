<?php

/*
|--------------------------------------------------------------------------
| Feature flags (per-clone)
|--------------------------------------------------------------------------
|
| Toggle optional business modules on/off per site clone. Read by
| app/Providers/RouteServiceProvider.txt (the map() that becomes active
| after the install wizard runs, see InstallController::step5, which
| copies this file over RouteServiceProvider.php). A disabled module's
| routes are never registered, so its pages 404 and its admin menu
| entries (still visible, not yet gated) lead nowhere until removed.
|
| These only gate the optional modules that change what kind of store
| a site is (marketplace vs. single-vendor, auction vs. plain catalog,
| etc). Always-on infrastructure (auth, cart, checkout, admin, payment
| gateway callback routes) is intentionally not flagged here.
|
*/

return [
    'multivendor' => env('FEATURE_MULTIVENDOR', true),
    'auction' => env('FEATURE_AUCTION', true),
    'wholesale' => env('FEATURE_WHOLESALE', true),
    'affiliate' => env('FEATURE_AFFILIATE', true),
    'pos' => env('FEATURE_POS', true),
    'preorder' => env('FEATURE_PREORDER', true),
    'club_points' => env('FEATURE_CLUB_POINTS', true),
    'delivery_boy' => env('FEATURE_DELIVERY_BOY', true),
    'refund_request' => env('FEATURE_REFUND_REQUEST', true),
];
