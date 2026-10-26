<x-layouts.app title="Share your birthday · Let’s Run Ibadan" robots="noindex">
    <x-site.header />

    <main class="sec bday">
        <div class="wrap bday-wrap">
            <div class="sec-head">
                <p class="label">Birthdays</p>
                <h1 class="bday-title">Your birthday, <span class="wm-hl">our party.</span></h1>
                <p class="sec-lede">Tell us when your birthday is and send a photo or two. We’ll shout you out on the @@letsrunibadan WhatsApp community.</p>
            </div>

            @if (session('status'))
                <p class="bday-notice bday-notice-ok" role="status">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <p class="bday-notice bday-notice-error" role="alert">Something needs fixing. Check the fields marked below.</p>
            @endif

            <form class="bday-card" method="POST" action="{{ route('birthdays.store') }}" enctype="multipart/form-data">
                @csrf

                <fieldset class="bday-group">
                    <legend>About you</legend>

                    <x-form-field name="full_name" label="Full name" required>
                        <input class="input" id="full_name" name="full_name" type="text" value="{{ old('full_name') }}" required autocomplete="name">
                    </x-form-field>

                    <x-form-field name="preferred_name" label="What should we call you?" hint="The name for the shout-out, if it’s not your full name.">
                        <input class="input" id="preferred_name" name="preferred_name" type="text" value="{{ old('preferred_name') }}" autocomplete="nickname">
                    </x-form-field>

                    <x-form-field name="email" label="Email" required>
                        <input class="input" id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
                    </x-form-field>

                    <x-form-field name="phone" label="Phone (WhatsApp)">
                        <input class="input" id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel">
                    </x-form-field>

                    <x-form-field name="instagram_handle" label="Instagram handle" hint="So we can tag you in the birthday post.">
                        <input class="input" id="instagram_handle" name="instagram_handle" type="text" value="{{ old('instagram_handle') }}" placeholder="@yourname" autocapitalize="none" spellcheck="false">
                    </x-form-field>

                    <x-form-field name="tribe" label="Your tribe">
                        <select class="input" id="tribe" name="tribe">
                            <option value="">Not sure yet</option>
                            @foreach ($tribes as $tribe)
                                <option value="{{ $tribe }}" @selected(old('tribe') === $tribe)>{{ $tribe }}</option>
                            @endforeach
                        </select>
                    </x-form-field>
                </fieldset>

                <fieldset class="bday-group bday-group-date">
                    <legend>Your birthday</legend>

                    <x-form-field name="birth_day" label="Day" required>
                        <select class="input" id="birth_day" name="birth_day" required>
                            <option value="">Day</option>
                            @foreach (range(1, 31) as $dayNumber)
                                <option value="{{ $dayNumber }}" @selected((int) old('birth_day') === $dayNumber)>{{ $dayNumber }}</option>
                            @endforeach
                        </select>
                    </x-form-field>

                    <x-form-field name="birth_month" label="Month" required>
                        <select class="input" id="birth_month" name="birth_month" required>
                            <option value="">Month</option>
                            @foreach ($months as $monthNumber => $monthName)
                                <option value="{{ $monthNumber }}" @selected((int) old('birth_month') === $monthNumber)>{{ $monthName }}</option>
                            @endforeach
                        </select>
                    </x-form-field>

                    <x-form-field name="birth_year" label="Year" hint="Only if you want us to know.">
                        <input class="input" id="birth_year" name="birth_year" type="number" inputmode="numeric" min="1900" max="{{ now()->year }}" value="{{ old('birth_year') }}">
                    </x-form-field>
                </fieldset>

                <fieldset class="bday-group bday-group-wide">
                    <legend>Photos</legend>

                    <x-form-field name="photos" error-key="photos" :label="'Up to '.$maxPhotos.' photos of you'" required
                        hint="JPG, PNG or WebP, 5 MB or less each. A clear, smiling one works best for the post.">
                        <input class="input-file" id="photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required data-max-photos="{{ $maxPhotos }}">
                        @foreach (array_unique(\Illuminate\Support\Arr::flatten($errors->get('photos.*'))) as $photoError)
                            <p class="field-error">{{ $photoError }}</p>
                        @endforeach
                    </x-form-field>

                    <div class="bday-previews" id="bday-previews" aria-live="polite"></div>
                </fieldset>

                <fieldset class="bday-group bday-group-wide">
                    <legend>Anything else?</legend>

                    <x-form-field name="notes" label="Notes for the organisers"
                        hint="Anything we should know. For example, if it’s a milestone birthday, whether we can mention your age, or how you’d like to be celebrated.">
                        <textarea class="input" id="notes" name="notes" rows="4">{{ old('notes') }}</textarea>
                    </x-form-field>

                    <div class="field">
                        <label class="bday-consent">
                            <input type="checkbox" name="photo_consent" value="1" @checked(old('photo_consent')) required>
                            <span class="box" aria-hidden="true"></span>
                            <span>I’m happy for LRI to share my photos and birthday (day and month only) on the club’s pages and group chats.</span>
                        </label>
                        @error('photo_consent')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>
                </fieldset>

                <button class="btn btn-yellow" type="submit">Save my birthday</button>
            </form>
        </div>
    </main>

    <x-site.footer />
</x-layouts.app>
