<x-layouts.app title="Gallery · Let’s Run Ibadan" description="Photos from Let’s Run Ibadan’s Saturday runs, hangouts and events around Ibadan." :canonical="route('gallery')">
    <x-site.header />

    <main>
        <section class="gl-hero" id="top">
            <div class="wrap gl-hero-copy">
                <p class="label">The gallery</p>
                <h1 class="wordmark gl-title"><span class="wm-line">Every Saturday,</span><span class="wm-line">in <span class="wm-hl">pictures.</span></span></h1>
                <p class="lede">Early starts, big smiles and the people who keep showing up. This is what running with Let’s Run Ibadan looks like.</p>
                <ul class="hero-facts">
                    <li>Every pace</li>
                    <li>One community</li>
                </ul>
            </div>
        </section>

        <section class="gl-wall" aria-label="Photos">
            <div class="wrap">
                <ul class="gl-grid">
                    @foreach ($photos as $photo)
                        <li>
                            <a class="gl-tile" href="{{ \App\Support\SiteAsset::url("gallery/{$photo['name']}-large.webp") }}" data-gallery-photo>
                                <x-gallery-photo :photo="$photo" sizes="(max-width: 600px) 50vw, (max-width: 1000px) 33vw, 300px" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        <section class="sec join" id="join">
            <div class="wrap">
                <div class="sec-head">
                    <p class="label">Your turn</p>
                    <h2>Get in the <em>next photo.</em></h2>
                    <p class="sec-lede">Fill the member form, pick your tribe and come run with us at 6:30 AM on Saturday.</p>
                </div>
                <div class="fd-join-cta">
                    <a class="btn" href="{{ config('lri.links.member_form') }}" target="_blank" rel="noopener">Become a member ↗</a>
                    <a class="btn" href="{{ route('home') }}#tribes">Find your tribe</a>
                </div>
            </div>
        </section>
    </main>

    <x-site.footer />

    <dialog class="gl-viewer" id="gallery-viewer" aria-label="Photo viewer">
        <button class="gl-viewer-btn gl-viewer-close" type="button" data-viewer-close aria-label="Close">✕</button>
        <button class="gl-viewer-btn gl-viewer-prev" type="button" data-viewer-prev aria-label="Previous photo">←</button>
        <img class="gl-viewer-image" data-viewer-image src="data:," alt="" draggable="false">
        <button class="gl-viewer-btn gl-viewer-next" type="button" data-viewer-next aria-label="Next photo">→</button>
    </dialog>
</x-layouts.app>
