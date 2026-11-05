<x-layouts.app title="Your invitation · Let’s Run Ibadan" robots="noindex, nofollow">
    <x-site.header />

    <main class="sec">
        <div class="wrap invite-wrap">
            <div class="sec-head">
                <p class="label">Admin invitation</p>
                <h1 class="bday-title">You’re <span class="wm-hl">invited.</span></h1>
                <p class="sec-lede">{{ Str::ucfirst($invitation->inviterName()) }} invited you to the {{ config('lri.site.name') }} admin as <strong>{{ $invitation->roleNames() }}</strong>. Add your name and choose a password to accept.</p>
            </div>

            @if ($isPreview)
                <p class="bday-notice bday-notice-error">Preview only, with sample data. The buttons are switched off.</p>
            @endif

            @error('invitation')
                <p class="bday-notice bday-notice-error" role="alert">{{ $message }}</p>
            @enderror

            <form class="bday-card invite-card" method="POST" action="{{ $acceptUrl }}">
                @csrf

                <x-form-field name="email" label="Email address" hint="You’ll sign in with this address." required>
                    <input class="input" id="email" type="email" value="{{ $invitation->email }}" autocomplete="username" disabled>
                </x-form-field>

                <x-form-field name="name" label="Your full name" required>
                    <input class="input" id="name" name="name" type="text" value="{{ old('name') }}" maxlength="255" autocomplete="name" required autofocus>
                </x-form-field>

                <x-form-field name="password" label="Choose a password" hint="At least 12 characters." required>
                    <input class="input" id="password" name="password" type="password" minlength="12" autocomplete="new-password" required>
                </x-form-field>

                <x-form-field name="password_confirmation" label="Type it again" required>
                    <input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                </x-form-field>

                <div class="invite-actions">
                    <button class="btn btn-yellow" type="submit" @disabled($isPreview)>Accept and sign in</button>
                    <a class="invite-link" href="{{ $declineUrl }}">No thanks, decline this invitation</a>
                </div>
            </form>
        </div>
    </main>

    <x-site.footer />
</x-layouts.app>
