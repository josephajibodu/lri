<x-mail::message :preheader="'Your ticket QR code for the '.$eventName.'.'" :banner-url="$bannerUrl" :banner-alt="$eventName">
<x-mail::eyebrow :accent="$accent">A ticket for you</x-mail::eyebrow>

# You’re in, {{ $firstName }}.

{{ $buyerName }} got you a ticket for the **{{ $eventName }}**.

<x-mail::ticket-pass :event-name="$eventName" :details="$details" :ticket="$ticket" :message="$message ?? null" :accent="$accent" :gradient="$gradient" />

<x-mail::button :url="$ticketUrl" :accent="$accent" :gradient="$gradient">
See your ticket
</x-mail::button>

<x-mail::note>Keep this email. The button opens your ticket any time.</x-mail::note>

@if ($channels)
<x-mail::channels :channels="$channels" :accent="$accent" />

@endif
<x-mail::sign-off :tagline="$tagline" />
</x-mail::message>
