<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Thin client for the self-hosted LicenseBox server (license.fikirmeclisi.com).
 *
 * One LicenseBox product represents this whole base template; each site
 * clone is a separate activation (its own license_code + client_name, see
 * config/license.php). Ports the relevant subset of LicenseBox's own
 * external_api_helper_sample.php to Laravel's HTTP client, and stores the
 * returned license file under storage/app directly via storage_path() —
 * NOT the 'local' Storage disk, which this project's config/filesystems.php
 * remaps to public_path() (for product-image uploads); using Storage::disk
 * ('local') here would silently publish the license file at a public URL.
 */
class LicenseService
{
    private const LICENSE_FILE = 'license.lic';

    public function checkConnection(): array
    {
        return $this->call('check_connection_ext');
    }

    public function activate(): array
    {
        $response = $this->call('activate_license', [
            'product_id' => config('license.product_id'),
            'license_code' => config('license.code'),
            'client_name' => config('license.client_name'),
            'verify_type' => app()->environment(),
        ]);

        if (! empty($response['status']) && ! empty($response['lic_response'])) {
            File::put($this->licenseFilePath(), trim($response['lic_response']));
        } else {
            File::delete($this->licenseFilePath());
        }

        return $response;
    }

    public function verify(): array
    {
        $payload = $this->licensePayload();
        if ($payload === null) {
            return ['status' => false, 'message' => 'No license activated on this site yet.'];
        }

        return $this->call('verify_license', $payload);
    }

    public function deactivate(): array
    {
        $payload = $this->licensePayload();
        if ($payload === null) {
            return ['status' => false, 'message' => 'No license activated on this site yet.'];
        }

        $response = $this->call('deactivate_license', $payload);

        if (! empty($response['status'])) {
            File::delete($this->licenseFilePath());
        }

        return $response;
    }

    public function hasLocalLicenseFile(): bool
    {
        return File::exists($this->licenseFilePath());
    }

    private function licenseFilePath(): string
    {
        return storage_path('app/' . self::LICENSE_FILE);
    }

    private function licensePayload(): ?array
    {
        if (File::exists($this->licenseFilePath())) {
            return [
                'product_id' => config('license.product_id'),
                'license_file' => File::get($this->licenseFilePath()),
                'license_code' => null,
                'client_name' => null,
            ];
        }

        if (config('license.code') && config('license.client_name')) {
            return [
                'product_id' => config('license.product_id'),
                'license_file' => null,
                'license_code' => config('license.code'),
                'client_name' => config('license.client_name'),
            ];
        }

        return null;
    }

    private function call(string $endpoint, array $payload = []): array
    {
        $url = rtrim(config('license.api_url'), '/') . '/api/' . $endpoint;

        try {
            $response = Http::withHeaders([
                'LB-API-KEY' => config('license.api_key'),
                'LB-URL' => config('app.url'),
                'LB-IP' => $this->currentIp(),
                'LB-LANG' => config('app.locale', 'en'),
            ])
                ->timeout(30)
                ->connectTimeout(30)
                ->post($url, $payload);
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => 'Connection to the license server failed.'];
        }

        if (! $response->successful()) {
            return ['status' => false, 'message' => 'Invalid response from the license server.'];
        }

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : ['status' => false, 'message' => 'Invalid response from the license server.'];
    }

    private function currentIp(): string
    {
        try {
            return request()->ip() ?: gethostbyname(gethostname());
        } catch (\Throwable $e) {
            return gethostbyname(gethostname());
        }
    }
}
