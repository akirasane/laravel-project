<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule security cleanup tasks
use Illuminate\Console\Scheduling\Schedule;

app(Schedule::class)->command('security:cleanup')->daily()->at('02:00');
app(Schedule::class)->command('sanctum:prune-expired --hours=24')->daily()->at('02:30');
