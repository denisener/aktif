<?php

namespace App\Console\Commands;

use App\Models\BusinessSetting;
use App\Models\Currency;
use App\Models\User;
use Artisan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use ZipArchive;

class InstallSite extends Command
{
    protected $signature = 'site:install
        {--with-demo : Also import public/demo.sql sample catalog and public/uploads.zip images}
        {--force : Run even if the database already looks installed}';

    protected $description = 'Non-interactive equivalent of the web install wizard, for scripted clone provisioning: imports shop.sql, creates the admin user, activates the full route set, and applies branding.';

    public function handle(): int
    {
        if (Schema::hasTable('business_settings') && ! $this->option('force')) {
            $this->error('business_settings table already exists — this site looks already installed. Use --force to reinstall anyway.');
            return self::FAILURE;
        }

        $adminEmail = config('site_provision.admin_email');
        $adminPassword = config('site_provision.admin_password');
        if (! $adminEmail || ! $adminPassword) {
            $this->error('SITE_ADMIN_EMAIL and SITE_ADMIN_PASSWORD must be set in .env before running site:install.');
            return self::FAILURE;
        }

        $this->info('Importing base schema (shop.sql)...');
        DB::unprepared(file_get_contents(base_path('shop.sql')));

        if ($this->option('with-demo')) {
            $this->info('Importing demo catalog (public/demo.sql)...');
            DB::unprepared(file_get_contents(base_path('public/demo.sql')));

            $zipPath = base_path('public/uploads.zip');
            if (File::exists($zipPath)) {
                $zip = new ZipArchive;
                $zip->open($zipPath);
                $zip->extractTo(public_path('uploads/all/'));
                $zip->close();
            }
        }

        $currencyCode = config('site_provision.currency');
        if ($currencyCode) {
            $currency = Currency::where('code', $currencyCode)->first();
            if (! $currency) {
                $this->warn("Currency code '{$currencyCode}' not found in currencies table; keeping the shop.sql default.");
            } else {
                BusinessSetting::where('type', 'system_default_currency')->update(['value' => $currency->id]);
                BusinessSetting::where('type', 'home_default_currency')->update(['value' => $currency->id]);
            }
        }

        $this->info('Creating admin user...');
        $user = new User;
        $user->name = config('site_provision.admin_name');
        $user->email = $adminEmail;
        $user->password = Hash::make($adminPassword);
        $user->user_type = 'admin';
        $user->email_verified_at = now();
        $user->save();
        $user->assignRole(['Super Admin']);

        $this->info('Activating full route set...');
        File::copy(base_path('app/Providers/RouteServiceProvider.txt'), base_path('app/Providers/RouteServiceProvider.php'));

        $this->call('site:brand');

        Artisan::call('optimize:clear');

        $this->info('Site installed.');

        return self::SUCCESS;
    }
}
