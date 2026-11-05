<x-layouts.app title="Decline your invitation · Let’s Run Ibadan" robots="noindex, nofollow">
    <x-site.header />

    <main class="sec">
        <div class="wrap invite-wrap">
            <div class="sec-head">
                <p class="label">Admin invitation</p>
                <h1 class="bday-title">Decline this invitation?</h1>
                <p class="sec-lede">{{ Str::ucfirst($invitation->inviterName()) }} will see that you said no, and this link will stop working.</p>
            </div>

            @if ($isPreview)
                <p class="bday-notice bday-notice-error">Preview only, with sample data. The buttons are switched off.</p>
            @endif

            <form class="invite-actions" method="POST" action="{{ $declineUrl }}">
                @csrf
                <button class="btn btn-yellow" type="submit" @disabled($isPreview)>Yes, decline</button>
                <a class="btn" href="{{ $acceptUrl }}">Go back</a>
            </form>
        </div>
    </main>

    <x-site.footer />
</x-layouts.app>
