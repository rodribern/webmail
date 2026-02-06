<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Limpa anexos temporários órfãos (mais de 24h)
Schedule::call(function () {
    $tempDir = storage_path('app/private/temp_attachments');

    if (!is_dir($tempDir)) {
        return;
    }

    foreach (glob("{$tempDir}/*") as $dir) {
        if (is_dir($dir) && filemtime($dir) < now()->subHours(24)->timestamp) {
            File::deleteDirectory($dir);
        }
    }
})->hourly()->name('cleanup-temp-attachments');
