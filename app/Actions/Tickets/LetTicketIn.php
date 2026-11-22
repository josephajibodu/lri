<?php

namespace App\Actions\Tickets;

use App\Enums\TicketScanResult;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Check a ticket scanned at the gate and, if it's good, record its holder as let in. A ticket gets one person in,
 * once. The ticket row is locked, so two people scanning the same QR code at two gates can't both be let in.
 *
 * Until check-in opens, a good ticket is reported as valid but nothing is recorded, so the scanner can be tried out
 * on real tickets without using them up.
 */
class LetTicketIn
{
    public function handle(Ticket $ticket, User $staff, bool $recordEntry): TicketScanResult
    {
        return DB::transaction(function () use ($ticket, $staff, $recordEntry): TicketScanResult {
            $locked = Ticket::query()->with('order')->lockForUpdate()->findOrFail($ticket->id);

            if ($locked->isAdmitted()) {
                return TicketScanResult::AlreadyLetIn;
            }

            $result = TicketScanResult::forOrderStatus($locked->order->status);

            if (! $result->isValid()) {
                return $result;
            }

            if (! $recordEntry) {
                return TicketScanResult::ValidPractice;
            }

            $locked->update(['admitted_at' => now(), 'admitted_by' => $staff->id]);

            return TicketScanResult::LetIn;
        });
    }
}
