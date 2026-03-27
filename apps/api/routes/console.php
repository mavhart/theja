<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\CommunicationService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    app(CommunicationService::class)->scheduleReminders();
})->dailyAt('08:00');

Schedule::call(function () {
    app(CommunicationService::class)->scheduleLacReminders();
})->dailyAt('08:10');

Schedule::call(function () {
    app(CommunicationService::class)->schedulePrescriptionReminders();
})->dailyAt('08:20');

Schedule::call(function () {
    app(CommunicationService::class)->scheduleBirthdays();
})->dailyAt('08:30');
