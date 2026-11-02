<?php

use App\Models\BarracksRegistration;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('barracks:prune-passports', function () {
    $abandonedPassports = collect(Storage::files(BarracksRegistration::PendingPassportDirectory))
        ->filter(fn (string $path): bool => Storage::lastModified($path) < now()->subDay()->getTimestamp());

    Storage::delete($abandonedPassports->all());

    $this->info("Deleted {$abandonedPassports->count()} passport photographs that were never submitted.");
})->purpose('Delete barracks passport photographs uploaded over a day ago whose form was never submitted');

Schedule::command('barracks:prune-passports')->daily();
