@props(['name', 'label', 'hint' => null, 'required' => false, 'errorKey' => null])

<div {{ $attributes->class('field') }}>
    <label class="field-label" for="{{ $name }}">
        {{ $label }}
        @unless ($required)
            <span class="field-optional">Optional</span>
        @endunless
    </label>

    {{ $slot }}

    @if ($hint)
        <p class="field-hint" id="{{ $name }}-hint">{{ $hint }}</p>
    @endif

    @error($errorKey ?? $name)
        <p class="field-error">{{ $message }}</p>
    @enderror
</div>
