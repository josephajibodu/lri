<?php

namespace App\Console\Commands;

use App\Actions\Checkout\RefreshPayment;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\Exceptions\PaymentProviderException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payments:reconcile {--limit=200 : The most payments to check in one run}')]
#[Description('Ask providers about open payments that haven’t been checked lately, in case a webhook went missing')]
class ReconcilePayments extends Command
{
    /**
     * Open payments younger than this many days are checked; older ones are left for the abandon step and admins.
     */
    private const LookBackDays = 2;

    /**
     * Execute the console command.
     */
    public function handle(RefreshPayment $refreshPayment): int
    {
        $checked = 0;
        $settled = 0;

        Payment::query()
            ->where('status', PaymentStatus::Open)
            ->whereNotNull('provider_checkout_id')
            ->whereBetween('created_at', [now()->subDays(self::LookBackDays), now()->subMinutes(2)])
            ->where(fn ($query) => $query->whereNull('last_checked_at')->orWhere('last_checked_at', '<=', now()->subMinutes(5)))
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get()
            ->each(function (Payment $payment) use ($refreshPayment, &$checked, &$settled): void {
                try {
                    $checked++;
                    $settled += $refreshPayment->handle($payment)->status !== PaymentStatus::Open ? 1 : 0;
                } catch (PaymentProviderException $exception) {
                    report($exception);
                }
            });

        $this->info("Checked {$checked} open payments; {$settled} have now settled.");

        return self::SUCCESS;
    }
}
