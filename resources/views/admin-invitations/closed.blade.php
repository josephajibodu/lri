<x-layouts.app title="Your invitation · Let’s Run Ibadan" robots="noindex, nofollow">
    <x-site.header />

    <main class="sec">
        <div class="wrap invite-wrap">
            <div class="sec-head">
                <p class="label">Admin invitation</p>
                <h1 class="bday-title">{{ $heading }}</h1>
                <p class="sec-lede">{{ $message }}</p>
            </div>

            @if ($status === App\Enums\AdminInvitationStatus::Accepted)
                <a class="btn btn-yellow" href="{{ route('filament.admin.auth.login') }}">Go to sign in</a>
            @else
                <a class="btn" href="{{ route('home') }}">Go to the website</a>
            @endif
        </div>
    </main>

    <x-site.footer />
</x-layouts.app>
