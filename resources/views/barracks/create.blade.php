<x-layouts.app title="Akobo Barracks registration · Let’s Run Ibadan" robots="noindex">
    <x-site.header />

    <main class="sec bday">
        <div class="wrap bday-wrap">
            <div class="sec-head">
                <p class="label">Akobo tribe</p>
                <h1 class="bday-title">Barracks <span class="wm-hl">registration.</span></h1>
                <p class="sec-lede">
                    {{ $details['cantonment'] }} now asks every runner to register before running inside the barracks.
                    Fill this in and add a passport photograph, and the organisers will print your form and drop it off for you.
                </p>
                <p class="sec-lede">
                    The barracks charges a one-time registration fee of {{ $details['fee'] }}. The organisers will share how to pay in the Akobo group.
                    @if ($details['deadline'])
                        Please register by <strong>{{ $details['deadline'] }}</strong>.
                    @endif
                </p>
            </div>

            @if (session('status'))
                <p class="bday-notice bday-notice-ok" role="status">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <p class="bday-notice bday-notice-error" role="alert">Something needs fixing. Check the fields marked below.</p>
            @endif

            <form class="bday-card" method="POST" action="{{ route('barracks.store') }}" enctype="multipart/form-data">
                @csrf

                <fieldset class="bday-group">
                    <legend>Your details</legend>

                    <x-form-field class="bday-span" name="full_name" label="Name" hint="Your full name, as it should appear on the permit." required>
                        <input class="input" id="full_name" name="full_name" type="text" value="{{ old('full_name') }}" required autocomplete="name">
                    </x-form-field>

                    <x-form-field name="state_of_origin" label="State of origin" required>
                        <input class="input" id="state_of_origin" name="state_of_origin" type="text" value="{{ old('state_of_origin') }}" required>
                    </x-form-field>

                    <x-form-field name="lga" label="LGA" hint="Local government area of your state of origin." required>
                        <input class="input" id="lga" name="lga" type="text" value="{{ old('lga') }}" required>
                    </x-form-field>

                    <x-form-field class="bday-span" name="residential_address" label="Residential address" required>
                        <input class="input" id="residential_address" name="residential_address" type="text" value="{{ old('residential_address') }}" required autocomplete="street-address">
                    </x-form-field>

                    <x-form-field name="occupation" label="Occupation" required>
                        <input class="input" id="occupation" name="occupation" type="text" value="{{ old('occupation') }}" required autocomplete="organization-title">
                    </x-form-field>

                    <x-form-field name="phone" label="Phone number" required>
                        <input class="input" id="phone" name="phone" type="tel" value="{{ old('phone') }}" required autocomplete="tel">
                    </x-form-field>
                </fieldset>

                <fieldset class="bday-group bday-group-wide">
                    <legend>Passport photograph</legend>

                    <x-form-field name="passport" label="A recent passport photograph" required
                        hint="A clear photo of your face on a plain background. JPG, PNG or WebP, 5 MB or less. It uploads as soon as you choose it and will be printed on your form.">
                        <input type="hidden" id="passport_upload" name="passport_upload" value="{{ $pendingPassport }}">
                        <input class="input-file" id="passport" name="passport" type="file" accept="image/jpeg,image/png,image/webp" @required(! $pendingPassport)>
                        @error('passport_upload')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </x-form-field>

                    <div class="upload-status" id="passport-status" data-upload-url="{{ route('barracks.passport') }}" data-state="{{ $pendingPassport ? 'done' : 'idle' }}" @unless ($pendingPassport) hidden @endunless>
                        <img class="upload-preview" id="passport-preview" src="{{ $pendingPassportUrl }}" alt="Your passport photograph" @unless ($pendingPassportUrl) hidden @endunless>
                        <div class="upload-body">
                            <p class="upload-text" id="passport-status-text" role="status">{{ $pendingPassport ? 'Photo uploaded. Choose another to replace it.' : '' }}</p>
                            <progress class="upload-bar" id="passport-progress" max="100" value="0" hidden>0%</progress>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="bday-group bday-group-wide">
                    <legend>Undertaking / declaration</legend>

                    <div class="field">
                        <label class="bday-consent">
                            <input type="checkbox" name="declaration" value="1" @checked(old('declaration')) required>
                            <span class="box" aria-hidden="true"></span>
                            <span>I hereby declare that the information provided above is true. I also undertake to abide by all rules and regulations guiding the operation in {{ $details['cantonment'] }}.</span>
                        </label>
                        @error('declaration')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-form-field name="signature_name" label="Signature" hint="Type your full name to sign. Today’s date is added for you." required>
                        <input class="input" id="signature_name" name="signature_name" type="text" value="{{ old('signature_name') }}" required autocomplete="name">
                    </x-form-field>
                </fieldset>

                <button class="btn btn-yellow" type="submit">Submit my registration</button>
            </form>
        </div>
    </main>

    <x-site.footer />
</x-layouts.app>
