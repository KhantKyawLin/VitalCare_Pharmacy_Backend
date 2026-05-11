<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Schedule Daily Database Backups at Midnight
\Illuminate\Support\Facades\Schedule::command('app:db-backup')->daily();
