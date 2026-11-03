<?php

use App\Http\Controllers\BarracksRegistrationController;
use App\Http\Controllers\BirthdayController;
use App\Http\Controllers\DiscoRunController;
use App\Http\Controllers\FakeCheckoutController;
use App\Http\Controllers\FoundersController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaceSettersController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/founders', FoundersController::class)->name('founders');
Route::get('/pace-setters', PaceSettersController::class)->name('pace-setters');
Route::get('/disco-run', DiscoRunController::class)->name('disco-run');

Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');

Route::get('/birthdays/share', [BirthdayController::class, 'create'])->name('birthdays.create');
Route::post('/birthdays', [BirthdayController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('birthdays.store');
Route::get('/birthdays', [BirthdayController::class, 'index'])
    ->middleware('auth.basic')
    ->name('birthdays.index');

Route::get('/akobo/barracks', [BarracksRegistrationController::class, 'create'])->name('barracks.create');
Route::post('/akobo/barracks', [BarracksRegistrationController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('barracks.store');
Route::post('/akobo/barracks/passport', [BarracksRegistrationController::class, 'uploadPassport'])
    ->middleware('throttle:20,1')
    ->name('barracks.passport');
Route::middleware('auth.basic')->group(function () {
    Route::get('/akobo/barracks/registrations', [BarracksRegistrationController::class, 'index'])->name('barracks.index');
    Route::get('/akobo/barracks/registrations/print/{registration?}', [BarracksRegistrationController::class, 'print'])->name('barracks.print');
});

Route::post('/disco-run/tickets', [OrderController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('orders.store');
Route::middleware('signed:checkout_id')->group(function () {
    Route::get('/orders/{order:reference}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order:reference}/status', [OrderController::class, 'status'])
        ->middleware('throttle:30,1')
        ->name('orders.status');
    Route::post('/orders/{order:reference}/payments', [OrderController::class, 'pay'])
        ->middleware('throttle:10,1')
        ->name('orders.pay');
});

Route::post('/webhooks/payments/{provider}', PaymentWebhookController::class)
    ->whereIn('provider', ['bachs', 'fake'])
    ->middleware('throttle:240,1')
    ->name('webhooks.payments');

if (! app()->isProduction()) {
    Route::get('/payments/fake/{checkoutId}', [FakeCheckoutController::class, 'show'])->name('payments.fake.show');
    Route::post('/payments/fake/{checkoutId}', [FakeCheckoutController::class, 'settle'])->name('payments.fake.settle');
}
