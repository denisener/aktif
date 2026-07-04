<?php

namespace App\Console\Commands;

use App\Services\LicenseService;
use Illuminate\Console\Command;

class LicenseCheck extends Command
{
    protected $signature = 'license:check {--activate : Activate using LICENSE_CODE/LICENSE_CLIENT_NAME from .env} {--ping : Only test connectivity to the license server, no activation/verification}';

    protected $description = 'Check connectivity to / status of the LicenseBox license server for this site.';

    public function handle(LicenseService $license): int
    {
        if ($this->option('ping')) {
            $result = $license->checkConnection();
            $this->line(json_encode($result));
            return ! empty($result['status']) ? self::SUCCESS : self::FAILURE;
        }

        if ($this->option('activate')) {
            $result = $license->activate();
            $this->line(json_encode($result));
            return ! empty($result['status']) ? self::SUCCESS : self::FAILURE;
        }

        $result = $license->verify();
        $this->line(json_encode($result));

        return ! empty($result['status']) ? self::SUCCESS : self::FAILURE;
    }
}
