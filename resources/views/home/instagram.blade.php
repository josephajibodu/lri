@if (count(config('lri.instagram_posts')) > 0)
    <section class="sec ig" id="instagram" aria-labelledby="instagram-title">
        <div class="wrap ig-head">
            <div class="sec-head">
                <p class="label">On Instagram</p>
                <h2 id="instagram-title">Fresh from <em>the road.</em></h2>
                <p class="sec-lede">Reels and photos from our runs, hangouts and cause days. Swipe through, then follow along at @@letsrunibadan.</p>
            </div>
            <div class="ig-controls">
                <button class="ig-arrow" type="button" data-rail-prev aria-controls="ig-rail" aria-label="Previous posts">←</button>
                <button class="ig-arrow" type="button" data-rail-next aria-controls="ig-rail" aria-label="Next posts">→</button>
            </div>
        </div>
        <div class="ig-rail" id="ig-rail" tabindex="0" role="region" aria-label="Instagram posts from Let’s Run Ibadan">
            @foreach (config('lri.instagram_posts') as $postUrl)
                <div class="ig-slide">
                    <blockquote class="instagram-media" data-instgrm-permalink="{{ $postUrl }}" data-instgrm-version="14">
                        <span class="ig-fallback">
                            <svg class="ig-glyph" viewBox="0 0 32 32" aria-hidden="true"><rect x="4" y="4" width="24" height="24" rx="7"></rect><circle cx="16" cy="16" r="5.5"></circle><path d="M22.5 9.5h.01"></path></svg>
                            <span class="ig-fallback-handle">@@letsrunibadan</span>
                            <a href="{{ $postUrl }}" target="_blank" rel="noopener">View this post on Instagram</a>
                        </span>
                    </blockquote>
                </div>
            @endforeach
            <a class="ig-more" href="{{ config('lri.links.instagram') }}" target="_blank" rel="noopener">
                <svg class="ig-glyph" viewBox="0 0 32 32" aria-hidden="true"><rect x="4" y="4" width="24" height="24" rx="7"></rect><circle cx="16" cy="16" r="5.5"></circle><path d="M22.5 9.5h.01"></path></svg>
                <span class="ig-more-handle">@@letsrunibadan</span>
                <span class="ig-more-text">Every run, reel and shout-out lives on our Instagram.</span>
                <span class="ig-more-cta">Follow us ↗</span>
            </a>
        </div>
    </section>
@endif
