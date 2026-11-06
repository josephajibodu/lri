@php
    $photos = collect(config('gallery.photos'));
    $featuredPhotos = $photos->where('featured', true)->take(5);
@endphp

@if ($featuredPhotos->isNotEmpty())
    <section class="sec gl-teaser" id="gallery" aria-labelledby="gallery-title">
        <div class="wrap">
            <div class="sec-head">
                <p class="label">The gallery</p>
                <h2 id="gallery-title">Come for the run, <em>stay for this.</em></h2>
                <p class="sec-lede">A small peek at what Saturdays with LRI look like. The rest of the album is one tap away.</p>
            </div>
            <div class="gl-mosaic">
                @foreach ($featuredPhotos as $photo)
                    <a class="gl-tile" href="{{ route('gallery') }}">
                        <x-gallery-photo :photo="$photo" :sizes="$loop->first ? '(max-width: 700px) 100vw, 600px' : '(max-width: 700px) 50vw, 300px'" />
                    </a>
                @endforeach
            </div>
            <div class="gl-teaser-cta">
                <a class="btn btn-yellow" href="{{ route('gallery') }}">See all {{ $photos->count() }} photos →</a>
            </div>
        </div>
    </section>
@endif
