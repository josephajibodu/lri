<?php

use App\Http\Controllers\BirthdayController;
use App\Http\Controllers\FoundersController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PaceSettersController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/founders', FoundersController::class)->name('founders');
Route::get('/pace-setters', PaceSettersController::class)->name('pace-setters');

Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');

Route::get('/birthdays/share', [BirthdayController::class, 'create'])->name('birthdays.create');
Route::post('/birthdays', [BirthdayController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('birthdays.store');
Route::get('/birthdays', [BirthdayController::class, 'index'])
    ->middleware('auth.basic')
    ->name('birthdays.index');
