<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Actions\Checkout\RefreshPayment;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Mail\TicketsMail;
use App\Models\Order;
use App\Payments\Exceptions\PaymentProviderException;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;

/**
 * @property Order $record
 */
class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkWithProvider')
                ->label('Check with provider')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (Order $record): bool => $record->payments()->where('status', PaymentStatus::Open)->whereNotNull('provider_checkout_id')->exists())
                ->action(function (Order $record, RefreshPayment $refreshPayment): void {
                    try {
                        $record->payments()->where('status', PaymentStatus::Open)->whereNotNull('provider_checkout_id')->get()
                            ->each(fn ($payment) => $refreshPayment->handle($payment));
                    } catch (PaymentProviderException $exception) {
                        Notification::make()->title('The provider didn’t answer')->body($exception->getMessage())->danger()->send();

                        return;
                    }

                    $record->refresh();
                    Notification::make()->title('Checked: the order is '.strtolower($record->status->label()))->success()->send();
                }),
            Action::make('resendTickets')
                ->label('Email tickets again')
                ->icon(Heroicon::OutlinedEnvelope)
                ->color('gray')
                ->visible(fn (Order $record): bool => $record->status === OrderStatus::Paid)
                ->requiresConfirmation()
                ->modalDescription(fn (Order $record): string => "The tickets go to {$record->buyer_email} again.")
                ->action(function (Order $record): void {
                    Mail::to($record->buyer_email, $record->buyer_name)->send(new TicketsMail($record->load(['ticketType', 'tickets'])));
                    $record->update(['tickets_emailed_at' => now()]);

                    Notification::make()->title('Tickets sent to '.$record->buyer_email)->success()->send();
                }),
            Action::make('releaseTickets')
                ->label('Release tickets')
                ->icon(Heroicon::OutlinedLockOpen)
                ->color('warning')
                ->visible(fn (Order $record): bool => $record->status === OrderStatus::Disputed)
                ->requiresConfirmation()
                ->modalHeading('Let these tickets work again?')
                ->modalDescription('Only do this once the dispute is over and Bachs has confirmed you keep the money. The buyer’s tickets work again straight away.')
                ->action(function (Order $record): void {
                    $record->update(['status' => OrderStatus::Paid]);
                    $record->flagForReview('Tickets released from their dispute by '.auth()->user()->name.' on '.now('Africa/Lagos')->format('j M, g:i A').'.');

                    Notification::make()->title('The tickets work again')->success()->send();
                }),
            Action::make('markLookedAt')
                ->label('Mark as looked at')
                ->icon(Heroicon::OutlinedCheck)
                ->color('gray')
                ->visible(fn (Order $record): bool => $record->review_note !== null)
                ->requiresConfirmation()
                ->modalDescription('Clears the note on this order. It doesn’t change the order or its tickets.')
                ->action(function (Order $record): void {
                    $record->update(['review_note' => null]);

                    Notification::make()->title('Note cleared')->success()->send();
                }),
            Action::make('openBuyerPage')
                ->label('Buyer’s order page')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (Order $record): string => $record->statusUrl())
                ->openUrlInNewTab(),
        ];
    }
}
