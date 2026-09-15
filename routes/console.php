<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule automated Abandoned Cart recovery scan every 15 minutes
Schedule::command('carts:process-abandoned')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

// Schedule automated cancellation of expired unpaid orders every 5 minutes
Schedule::command('orders:cancel-expired-unpaid')
    ->everyFiveMinutes()
    ->withoutOverlapping();
