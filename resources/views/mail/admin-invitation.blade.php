<x-mail::official.message :preheader="'Accept by '.$expiry.' to set up your login.'">
# You’re invited to join the team

{{ Str::ucfirst($inviter) }} has invited you to the {{ config('lri.site.name') }} admin, where our organisers look after the club’s events, tickets and members.

<x-mail::details :rows="$details" />

<x-mail::official.button :url="$acceptUrl">
Accept invitation
</x-mail::official.button>

<x-mail::official.secondary-link :url="$declineUrl" lead="Can’t take this on?">
Decline this invitation
</x-mail::official.secondary-link>

<x-mail::official.fine-print>
This invitation is only for {{ $email }}, so please don’t forward it. If you weren’t expecting it, you can ignore this email and nothing will change.
</x-mail::official.fine-print>
</x-mail::official.message>
