<?php

use App\Enums\AdminPermission;
use App\Http\Controllers\AdminInvitationController;
use App\Http\Controllers\BarracksRegistrationController;
use App\Http\Controllers\BirthdayController;
use App\Http\Controllers\CheckoutEventController;
use App\Http\Controllers\DiscoRunController;
use App\Http\Controllers\FoundersController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MailPreviewController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaceSettersController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\TicketController;
use App\Http\Middleware\EnsureBarracksRegistrationIsOpen;
use App\Http\Middleware\IdentifyVisitor;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/founders', FoundersController::class)->name('founders');
Route::get('/pace-setters', PaceSettersController::class)->name('pace-setters');
Route::get('/silent-disco-run', DiscoRunController::class)->middleware(IdentifyVisitor::class)->name('disco-run');
Route::permanentRedirect('/disco-run', '/silent-disco-run');

Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');

Route::get('/birthdays/share', [BirthdayController::class, 'create'])->name('birthdays.create');
Route::post('/birthdays', [BirthdayController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('birthdays.store');
Route::get('/birthdays', fn () => redirect('/admin/birthdays'));

Route::middleware(EnsureBarracksRegistrationIsOpen::class)->group(function () {
    Route::get('/akobo/barracks', [BarracksRegistrationController::class, 'create'])->name('barracks.create');
    Route::post('/akobo/barracks', [BarracksRegistrationController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('barracks.store');
    Route::post('/akobo/barracks/passport', [BarracksRegistrationController::class, 'uploadPassport'])
        ->middleware('throttle:20,1')
        ->name('barracks.passport');
    Route::get('/akobo/barracks/registrations', fn () => redirect('/admin/barracks-registrations'));
    Route::get('/akobo/barracks/registrations/print/{registration?}', [BarracksRegistrationController::class, 'print'])
        ->middleware(['auth', 'can:'.AdminPermission::ManageBarracksRegistrations->value])
        ->name('barracks.print');
});

Route::post('/silent-disco-run/tickets', [OrderController::class, 'store'])
    ->middleware(['throttle:10,1', IdentifyVisitor::class])
    ->name('orders.store');
Route::post('/checkout-events', CheckoutEventController::class)
    ->middleware(['throttle:60,1', IdentifyVisitor::class])
    ->name('checkout-events.store');
Route::middleware(['signed:checkout_id', IdentifyVisitor::class])->group(function () {
    Route::get('/orders/{order:reference}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order:reference}/status', [OrderController::class, 'status'])
        ->middleware('throttle:30,1')
        ->name('orders.status');
    Route::post('/orders/{order:reference}/payments', [OrderController::class, 'pay'])
        ->middleware('throttle:10,1')
        ->name('orders.pay');
});

Route::get('/receipts/{order:receipt_token}', [OrderController::class, 'receipt'])->name('receipts.show');
Route::scopeBindings()->middleware('throttle:20,1')->prefix('/receipts/{order:receipt_token}/tickets/{ticket}')->group(function () {
    Route::put('/', [TicketController::class, 'update'])->name('receipts.tickets.update');
    Route::post('/link', [TicketController::class, 'shareLink'])->name('receipts.tickets.share-link');
    Route::delete('/link', [TicketController::class, 'cancelLink'])->name('receipts.tickets.cancel-link');
    Route::post('/reissue', [TicketController::class, 'reissue'])->name('receipts.tickets.reissue');
});

Route::get('/tickets/{ticket:claim_token}', [TicketController::class, 'show'])->name('tickets.show');
Route::post('/tickets/{ticket:claim_token}', [TicketController::class, 'claim'])
    ->middleware('throttle:10,1')
    ->name('tickets.claim');

Route::prefix('/invitations/{token}')->group(function () {
    Route::get('/', [AdminInvitationController::class, 'show'])
        ->middleware('throttle:30,1')
        ->name('invitations.show');
    Route::post('/', [AdminInvitationController::class, 'accept'])
        ->middleware('throttle:10,1')
        ->name('invitations.accept');
    Route::get('/decline', [AdminInvitationController::class, 'confirmDecline'])
        ->middleware('throttle:30,1')
        ->name('invitations.decline-form');
    Route::post('/decline', [AdminInvitationController::class, 'decline'])
        ->middleware('throttle:10,1')
        ->name('invitations.decline');
});

Route::post('/webhooks/payments/{provider}', PaymentWebhookController::class)
    ->whereIn('provider', ['bachs'])
    ->middleware('throttle:240,1')
    ->name('webhooks.payments');

if (! app()->isProduction()) {
    Route::get('/mail-previews/admin-invitation/{page}', [MailPreviewController::class, 'invitationPage'])
        ->whereIn('page', ['accept', 'decline'])
        ->name('mail-previews.invitation-page');
    Route::get('/mail-previews/{preview?}', [MailPreviewController::class, 'index'])->name('mail-previews.index');
    Route::get('/mail-previews/{preview}/render', [MailPreviewController::class, 'show'])->name('mail-previews.show');
}
