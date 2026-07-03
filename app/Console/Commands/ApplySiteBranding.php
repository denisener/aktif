<?php

namespace App\Console\Commands;

use App\Models\BusinessSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class ApplySiteBranding extends Command
{
    protected $signature = 'site:brand {--force : Overwrite settings that already have a value}';

    protected $description = 'Apply SITE_* branding env vars (config/site_branding.php) into business_settings';

    private const MAP = [
        'site_name' => 'site_name',
        'website_name' => 'website_name',
        'header_logo' => 'header_logo',
        'footer_logo' => 'footer_logo',
        'system_logo_black' => 'system_logo_black',
        'system_logo_white' => 'system_logo_white',
        'base_color' => 'base_color',
        'secondary_base_color' => 'secondary_base_color',
        'contact_email' => 'contact_email',
        'contact_phone' => 'contact_phone',
        'contact_address' => 'contact_address',
        'facebook_link' => 'facebook_link',
        'meta_title' => 'meta_title',
        'meta_description' => 'meta_description',
        'meta_keywords' => 'meta_keywords',
    ];

    public function handle(): int
    {
        $config = config('site_branding');
        $force = (bool) $this->option('force');

        $theme = $config['theme'] ?? 'classic';
        if (! File::isDirectory(resource_path("views/frontend/{$theme}"))) {
            $this->error("Theme '{$theme}' not found under resources/views/frontend/. Aborting.");
            return self::FAILURE;
        }

        // Unlike the settings below, the theme is always applied: shop.sql already
        // ships with a non-empty default (e.g. 'thecore'), so a "don't overwrite an
        // existing value" guard here would mean SITE_THEME never takes effect after
        // the first run. Re-running site:brand with a changed SITE_THEME should
        // always switch the theme.
        $this->setSetting('homepage_select', $theme);

        $applied = 0;
        foreach (self::MAP as $configKey => $settingType) {
            $value = $config[$configKey] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $existing = BusinessSetting::where('type', $settingType)->whereNull('lang')->first();
            if ($existing && ! $force && filled($existing->value)) {
                continue;
            }

            $this->setSetting($settingType, $value);
            $applied++;
        }

        Cache::forget('business_settings');

        $this->info("Theme set to '{$theme}'. Applied {$applied} branding setting(s).");

        return self::SUCCESS;
    }

    /**
     * BusinessSetting has no $fillable, so mass-assignment helpers like
     * updateOrCreate() throw MassAssignmentException — set attributes
     * directly instead, same pattern the original installer code uses.
     */
    private function setSetting(string $type, string $value): void
    {
        $setting = BusinessSetting::where('type', $type)->whereNull('lang')->first();
        if (! $setting) {
            $setting = new BusinessSetting();
            $setting->type = $type;
            $setting->lang = null;
        }
        $setting->value = $value;
        $setting->save();
    }
}
