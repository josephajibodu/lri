{{-- A tracked link's QR code, for posters and flyers. Scans are counted and credited like taps. --}}
<div style="display: grid; justify-items: center; gap: 1rem; text-align: center;">
    <div style="width: 220px; padding: 12px; border-radius: 12px; background: #fff;" aria-label="QR code for {{ $shortUrl }}" role="img">
        {!! $svg !!}
    </div>
    <p style="margin: 0; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;">{{ $shortUrl }}</p>
    <x-filament::button tag="a" :href="'data:image/png;base64,'.$png" download="lri-{{ $slug }}-qr-code.png" icon="heroicon-o-arrow-down-tray">
        Download for printing
    </x-filament::button>
</div>
