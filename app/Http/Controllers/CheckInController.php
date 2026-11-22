<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\LetTicketIn;
use App\Enums\OrderStatus;
use App\Enums\TicketScanResult;
use App\Filament\Pages\HeadsetDesk;
use App\Http\Requests\ScanTicketRequest;
use App\Models\Ticket;
use App\Support\SiteAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * The check-in scanner: a phone page for staff on the gate, outside the admin but only for people who can use the
 * headset desk. It reads a ticket's QR code with the phone's camera and says straight away whether to let the person
 * in. It can be added to a phone's home screen, where it opens like an app.
 */
class CheckInController extends Controller
{
    public function show(): View
    {
        return view('check-in.show', [
            'checkInOpen' => HeadsetDesk::isCheckInOpen(),
            'counts' => $this->counts(),
        ]);
    }

    /**
     * Check one scanned or typed ticket, and let its holder in if it's good.
     */
    public function scan(ScanTicketRequest $request, LetTicketIn $letTicketIn): JsonResponse
    {
        $ticket = Ticket::findByCode($request->validated('code'));

        $result = $ticket
            ? $letTicketIn->handle($ticket, $request->user(), recordEntry: HeadsetDesk::isCheckInOpen())
            : TicketScanResult::NotFound;

        $ticket?->refresh()->load('admittedBy');

        return response()->json([
            'result' => $result->value,
            'valid' => $result->isValid(),
            'heading' => $result->heading(),
            'name' => $ticket?->holder_name,
            'code' => $ticket?->code,
            'detail' => $this->detail($result, $ticket),
            'counts' => $this->counts(),
        ]);
    }

    /**
     * Where the scanner opens from a phone's home screen. An app added to an iPhone's home screen keeps its own
     * sign-in, apart from Safari's, so it has to be able to reach the admin's sign-in page. Everyone else lands on the
     * website, as with the scanner's own address.
     */
    public function start(): RedirectResponse
    {
        if (HeadsetDesk::canAccess()) {
            return redirect()->route('check-in');
        }

        if (auth()->guest()) {
            return redirect()->guest(route('filament.admin.auth.login'));
        }

        return redirect()->route('home');
    }

    /**
     * What a phone needs to add the scanner to its home screen and open it full screen, like an app.
     */
    public function manifest(): JsonResponse
    {
        return response()->json([
            'id' => route('check-in', absolute: false),
            'name' => 'LRI Check-in',
            'short_name' => 'Check-in',
            'description' => 'Scan tickets in at the gate.',
            'start_url' => route('check-in.start', absolute: false),
            'scope' => route('check-in', absolute: false),
            'display' => 'standalone',
            'orientation' => 'portrait',
            'theme_color' => '#1C1917',
            'background_color' => '#1C1917',
            'icons' => [
                ['src' => SiteAsset::url('icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => SiteAsset::url('icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
            ],
        ], headers: ['Content-Type' => 'application/manifest+json'], options: JSON_UNESCAPED_SLASHES);
    }

    /**
     * The scanner's service worker, which phones look for before offering to install it. It sits under /check-in
     * but is allowed to look after /check-in itself.
     */
    public function serviceWorker(): Response
    {
        return response(file_get_contents(resource_path('js/check-in-service-worker.js')), headers: [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Service-Worker-Allowed' => route('check-in', absolute: false),
            'Cache-Control' => 'no-cache',
        ]);
    }

    /**
     * The line under the big word on the scanner's screen.
     */
    private function detail(TicketScanResult $result, ?Ticket $ticket): string
    {
        return match ($result) {
            TicketScanResult::LetIn => $ticket->isNamed()
                ? 'Let them in.'
                : 'Let them in. There’s no name on this ticket yet, so they’ll give one at the headset desk.',
            TicketScanResult::ValidPractice => 'Practice scan: nothing was recorded, because check-in hasn’t opened yet.',
            TicketScanResult::AlreadyLetIn => 'Someone got in on this ticket at '
                .$ticket->admitted_at->timezone('Africa/Lagos')->format('j M, g:i A')
                .($ticket->admittedBy ? ', scanned by '.$ticket->admittedBy->name : '')
                .'. Don’t let anyone else in on it.',
            TicketScanResult::NotPaid => 'This ticket’s order was never paid.',
            TicketScanResult::Refunded => 'This ticket’s order was refunded, so the ticket no longer works.',
            TicketScanResult::Disputed => 'The buyer’s bank is disputing the payment, so this ticket is on hold. Ask an organiser.',
            TicketScanResult::TestPayment => 'This ticket came from a test payment, so it doesn’t get anyone in.',
            TicketScanResult::NotFound => 'No ticket has this code. If the buyer reissued the ticket, its old QR code stopped working.',
        };
    }

    /**
     * How many paid tickets have got someone in so far.
     *
     * @return array{let_in: int, tickets: int}
     */
    private function counts(): array
    {
        $paid = Ticket::query()->whereRelation('order', 'status', OrderStatus::Paid);

        return [
            'let_in' => (clone $paid)->whereNotNull('admitted_at')->count(),
            'tickets' => $paid->count(),
        ];
    }
}
