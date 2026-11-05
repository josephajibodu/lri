<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Reports\SalesBySource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Download CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->downloadCsv()),
        ];
    }

    /**
     * The orders the table currently shows, with its search and filters, as a spreadsheet.
     */
    private function downloadCsv(): StreamedResponse
    {
        $query = $this->getFilteredSortedTableQuery()->with(['ticketType', 'latestPayment', 'coupon']);

        return response()->streamDownload(function () use ($query): void {
            $csv = fopen('php://output', 'w');
            fputcsv($csv, ['Reference', 'Status', 'Ticket', 'Quantity', 'Coupon', 'Discount', 'Total', 'Buyer', 'Email', 'Phone', 'Event emails', 'Method', 'Source', 'Campaign', 'Placed', 'Paid']);

            $query->lazyById(500)->each(function (Order $order) use ($csv): void {
                fputcsv($csv, [
                    $order->reference,
                    $order->status->label(),
                    $order->ticketType->name,
                    $order->quantity,
                    $order->coupon?->code,
                    $order->discount()->toDecimal(),
                    $order->total()->toDecimal(),
                    $order->buyer_name,
                    $order->buyer_email,
                    $order->buyer_phone,
                    $order->marketing_consent ? 'Yes' : 'No',
                    $order->latestPayment?->method?->label(),
                    SalesBySource::sourceOf($order),
                    $order->attribution['campaign'] ?? null,
                    $order->created_at->timezone('Africa/Lagos')->format('Y-m-d H:i'),
                    $order->paid_at?->timezone('Africa/Lagos')->format('Y-m-d H:i'),
                ]);
            });

            fclose($csv);
        }, 'lri-orders-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
