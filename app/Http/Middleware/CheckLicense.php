<?php

namespace App\Http\Middleware;

use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Periodically re-verifies this site's LicenseBox activation. Only active
 * when LICENSE_CODE/LICENSE_CLIENT_NAME are actually configured — an
 * unconfigured clone (e.g. local dev before provisioning) is not blocked.
 *
 * A successful verification is cached for config('license.verify_period_days')
 * so every request doesn't hit the license server. A failed/unreachable
 * check is intentionally NOT cached, so the next request retries rather
 * than locking the site out for the full period over a transient outage.
 */
class CheckLicense
{
    private const CACHE_KEY = 'license_status_valid';

    public function handle(Request $request, Closure $next)
    {
        if (! config('license.code') && ! config('license.client_name')) {
            return $next($request);
        }

        if (Cache::has(self::CACHE_KEY)) {
            return $next($request);
        }

        $result = app(LicenseService::class)->verify();

        if (empty($result['status'])) {
            abort(403, "This site's license could not be verified. Please contact the site owner.");
        }

        Cache::put(self::CACHE_KEY, true, now()->addDays((int) config('license.verify_period_days', 3)));

        return $next($request);
    }
}
