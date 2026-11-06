<?php

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function galleryPhoto(string $name, array $overrides = []): array
{
    return [
        'name' => $name,
        'width' => 1200,
        'height' => 1600,
        'alt' => "Runners in {$name}.",
        ...$overrides,
    ];
}

beforeEach(function () {
    config()->set('filesystems.disks.r2.url', 'https://assets.example.com');
});

test('the gallery page shows every photo from config in order, linking to the large version', function () {
    config()->set('gallery.photos', [galleryPhoto('first-photo'), galleryPhoto('second-photo')]);

    $this->get(route('gallery'))
        ->assertOk()
        ->assertSee('<title>Gallery · Let’s Run Ibadan</title>', false)
        ->assertSee('<link rel="canonical" href="'.route('gallery').'">', false)
        ->assertDontSeeText('2 photos')
        ->assertSeeInOrder(['Runners in first-photo.', 'Runners in second-photo.'])
        ->assertSee('href="https://assets.example.com/gallery/first-photo-large.webp" data-gallery-photo', false)
        ->assertSee('src="https://assets.example.com/gallery/first-photo-thumb.webp"', false)
        ->assertSee('width="1200" height="1600"', false);
});

test('the home page teases only the first five featured photos and links to the gallery', function () {
    config()->set('gallery.photos', [
        galleryPhoto('not-featured'),
        ...array_map(fn (int $number): array => galleryPhoto("featured-{$number}", ['featured' => true]), range(1, 6)),
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['Runners in featured-1.', 'Runners in featured-5.'])
        ->assertDontSee('Runners in featured-6.')
        ->assertDontSee('Runners in not-featured.')
        ->assertSeeText('See all photos')
        ->assertDontSeeText('7 photos')
        ->assertSee('href="'.route('gallery').'"', false);
});

test('the home page leaves the teaser out when no photo is featured', function () {
    config()->set('gallery.photos', [galleryPhoto('not-featured')]);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('id="gallery"', false);
});
