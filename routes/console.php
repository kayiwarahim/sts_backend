<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\CreateDatabaseBackupJob;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


/*
|--------------------------------------------------------------------------
| Automated Reconciliation
|--------------------------------------------------------------------------
|
| Internal reconciliation runs frequently because it is local and cheap.
| Provider reconciliation runs less frequently because it calls Relworx.
|
*/

Schedule::command(
    'water:reconcile --internal-only'
)
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command(
    'water:reconcile --provider-only'
)
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::job(
    new CreateDatabaseBackupJob(
        null,
        'scheduled',
        null
    )
)
    ->dailyAt('02:00')
    ->withoutOverlapping();