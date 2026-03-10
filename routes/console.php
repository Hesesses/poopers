<?php

use App\Jobs\CalculateDailyResults;
use App\Jobs\CheckProExpirations;
use App\Jobs\SendItemExpiryWarnings;
use App\Jobs\SendMorningResults;
use App\Jobs\SendSilentSyncPush;
use App\Jobs\SendSnapshotNotifications;
use App\Jobs\TakeNoonSnapshots;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new SendSilentSyncPush)->everyFifteenMinutes();

Schedule::job(new CalculateDailyResults)->hourly();

Schedule::job(new SendMorningResults)->hourly();

Schedule::job(new SendSnapshotNotifications)->hourly();

Schedule::job(new TakeNoonSnapshots)->hourly();

Schedule::job(new SendItemExpiryWarnings)->dailyAt('10:00');

Schedule::job(new CheckProExpirations)->daily();
