<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

test('pages load icons and link previews from the R2 bucket once its address is set', function () {
    config()->set('filesystems.disks.r2.url', 'https://assets.example.com/');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<link rel="icon" href="https://assets.example.com/favicon.ico"', false)
        ->assertSee('<link rel="icon" href="https://assets.example.com/favicon.svg"', false)
        ->assertSee('<link rel="apple-touch-icon" href="https://assets.example.com/apple-touch-icon.png"', false)
        ->assertSee('<meta property="og:image" content="https://assets.example.com/images/og-image.png">', false);
});

test('pages look for images on the site itself until the bucket address is set', function () {
    config()->set('filesystems.disks.r2.url', null);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<link rel="icon" href="'.asset('favicon.svg').'"', false);
});

test('the web manifest lists the icons from the R2 bucket', function () {
    config()->set('filesystems.disks.r2.url', 'https://assets.example.com');

    $this->get('/site.webmanifest')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('theme_color', '#FFB000')
        ->assertJsonPath('icons.0.src', 'https://assets.example.com/icon-192.png')
        ->assertJsonPath('icons.1.src', 'https://assets.example.com/icon-512.png');
});

test('old image and icon addresses redirect to the R2 bucket, so emails already sent keep their pictures', function (string $path) {
    config()->set('filesystems.disks.r2.url', 'https://assets.example.com');

    $this->get($path)->assertRedirect("https://assets.example.com{$path}");
})->with([
    '/favicon.ico',
    '/favicon.svg',
    '/apple-touch-icon.png',
    '/icon-192.png',
    '/icon-512.png',
    '/images/lri-logo-on-dark.png',
    '/images/disco-run/email-hero.jpg',
]);

test('old image and icon addresses are not found until the bucket address is set', function (string $path) {
    config()->set('filesystems.disks.r2.url', null);

    $this->get($path)->assertNotFound();
})->with(['/favicon.ico', '/images/lri-logo-on-dark.png']);

test('other addresses are not sent to the bucket', function () {
    config()->set('filesystems.disks.r2.url', 'https://assets.example.com');

    $this->get('/imagesomething')->assertNotFound();
    $this->get('/icon-999.png')->assertNotFound();
});

test('private uploads get short-lived signed links on the private bucket, and public files a plain address', function () {
    config()->set('filesystems.disks.r2.bucket', 'lri-assets');
    config()->set('filesystems.disks.r2.url', 'https://assets.example.com');
    config()->set('filesystems.disks.r2_private.bucket', 'lri-private');
    foreach (['r2', 'r2_private'] as $disk) {
        config()->set("filesystems.disks.{$disk}.key", 'test-key');
        config()->set("filesystems.disks.{$disk}.secret", 'test-secret');
        config()->set("filesystems.disks.{$disk}.endpoint", 'https://account.r2.cloudflarestorage.com');
    }

    expect(Storage::disk('r2_private')->temporaryUrl('barracks-passports/photo.jpg', now()->addHour()))
        ->toStartWith('https://account.r2.cloudflarestorage.com/lri-private/barracks-passports/photo.jpg?')
        ->toContain('X-Amz-Signature=')
        ->and(Storage::disk('r2')->url('images/logo.png'))
        ->toBe('https://assets.example.com/images/logo.png');
});

test('no images or icons are kept in the public folder', function () {
    $folders = array_filter([public_path(), public_path('images')], File::isDirectory(...));

    $imagesKept = collect($folders)
        ->flatMap(fn (string $folder): array => $folder === public_path() ? File::files($folder) : File::allFiles($folder))
        ->filter(fn (SplFileInfo $file): bool => in_array(strtolower($file->getExtension()), ['png', 'jpg', 'jpeg', 'webp', 'svg', 'gif', 'ico'], true))
        ->map(fn (SplFileInfo $file): string => $file->getFilename());

    expect($imagesKept)->toBeEmpty();
});
