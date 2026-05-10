<?php

use App\Console\Commands\PurgeTicketTrashCommand;
use App\Console\Commands\RunTicketInactiveAutomationsCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tickets:sla-monitor')->everyFiveMinutes();
Schedule::command(RunTicketInactiveAutomationsCommand::class)->everyFiveMinutes();
Schedule::command(PurgeTicketTrashCommand::class)->dailyAt('03:30');
