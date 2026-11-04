<x-mail::message :preheader="'Your ticket QR code for the '.$eventName.'.'" :banner-url="$bannerUrl" :banner-alt="$eventName">
# You’re in, {{ Str::before($ticket->holder_name, ' ') }}.

{{ $buyerName }} got you a ticket for the **{{ $eventName }}**. Show this QR code at the desk when you pick up your headset. If it won’t scan, they can type the code under it.

<x-mail::ticket-qr :ticket="$ticket" :message="$message ?? null" />

<x-mail::details :rows="$details" />

<x-mail::button :url="$ticketUrl">
See your ticket
</x-mail::button>

Keep this email. The button opens your ticket any time.

See you on the run,<br>
{{ config('lri.site.name') }}
</x-mail::message>
