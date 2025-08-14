<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ExportProfile;

class ExportBuildCommand extends Command
{
    protected $signature = 'export:build {profile_id}';
    protected $description = 'Belirli profilden XML üret ve yayınla';

    public function handle(): int
    {
        $profile = ExportProfile::findOrFail($this->argument('profile_id'));
        $run = app(\App\Services\ExportPublisher::class)->buildAndPublishFromProfile($profile);

        $this->info('OK: ' . $run->public_url);
        return self::SUCCESS;
    }
}
