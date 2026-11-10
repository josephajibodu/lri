<x-mail::message :preheader="'Your details are saved, so finishing takes a minute.'" :banner-url="$bannerUrl" :banner-alt="$eventName">
<x-mail::eyebrow :accent="$accent">Not paid yet</x-mail::eyebrow>

# Still coming, {{ $firstName }}?

You started getting {{ $tickets }} for the **{{ $eventName }}**, but the payment didn’t go through, so we couldn’t hold {{ $tickets === 'a ticket' ? 'it' : 'them' }} for you.

Good news: tickets are still on sale, and we’ve kept your details. Tap below to pick up where you left off.

<x-mail::details :rows="$details" />

<x-mail::button :url="$pickUpUrl" :accent="$accent" :gradient="$gradient">
Finish getting my {{ $tickets === 'a ticket' ? 'ticket' : 'tickets' }}
</x-mail::button>

<x-mail::note>Tickets go to whoever pays first, so they aren’t held until you finish.</x-mail::note>

<x-mail::panel>
Payment trouble? Try another way to pay on the ticket form.@if ($helpUrl) Or [message us on WhatsApp]({{ $helpUrl }}) and we’ll sort it out with you.@endif Already sorted, or changed your mind? Just ignore this. It’s the only reminder we’ll send.
</x-mail::panel>

@if ($channels)
<x-mail::channels :channels="$channels" :accent="$accent" />

@endif
<x-mail::sign-off :tagline="$tagline" />
</x-mail::message>
