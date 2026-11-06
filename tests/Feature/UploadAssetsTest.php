<?php

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToWriteFile;

beforeEach(function () {
    config()->set('filesystems.disks.r2.bucket', 'lri-assets');
    config()->set('filesystems.disks.r2_private.bucket', 'lri-private');

    $this->source = storage_path('framework/testing/assets-upload-'.uniqid());
    File::ensureDirectoryExists("{$this->source}/disco-run");
    File::put("{$this->source}/logo.png", 'logo');
    File::put("{$this->source}/disco-run/flyer-full.jpg", 'flyer');
});

afterEach(function () {
    File::deleteDirectory($this->source);
});

test('it uploads a folder into the bucket, keeping its layout and the local files', function () {
    Storage::fake('r2');

    $this->artisan('assets:upload', ['paths' => [$this->source], '--to' => 'images'])
        ->expectsOutputToContain('Uploaded 2 of 2 files to r2.')
        ->assertSuccessful();

    Storage::disk('r2')->assertExists('images/logo.png');
    Storage::disk('r2')->assertExists('images/disco-run/flyer-full.jpg');
    expect(File::exists("{$this->source}/logo.png"))->toBeTrue();
});

test('it uploads single files to the top of the bucket when no folder is given', function () {
    Storage::fake('r2');

    $this->artisan('assets:upload', ['paths' => ["{$this->source}/logo.png"]])->assertSuccessful();

    Storage::disk('r2')->assertExists('logo.png');
});

test('it sends public files with a cache lifetime, and makes the flyer download under its own name', function () {
    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('put')->once()->with(
        'images/logo.png',
        Mockery::type('resource'),
        ['CacheControl' => 'public, max-age=604800'],
    );
    $disk->shouldReceive('put')->once()->with(
        'images/disco-run/flyer-full.jpg',
        Mockery::type('resource'),
        ['CacheControl' => 'public, max-age=604800', 'ContentDisposition' => 'attachment; filename="lri-silent-disco-run-flyer.jpg"'],
    );
    $disk->shouldReceive('exists')->andReturnTrue();
    $disk->shouldReceive('size')->with('images/logo.png')->andReturn(4);
    $disk->shouldReceive('size')->with('images/disco-run/flyer-full.jpg')->andReturn(5);
    Storage::set('r2', $disk);

    $this->artisan('assets:upload', ['paths' => [$this->source], '--to' => 'images'])->assertSuccessful();
});

test('it sends private uploads to the private bucket without public caching', function () {
    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('put')->once()->with('logo.png', Mockery::type('resource'), []);
    $disk->shouldReceive('exists')->andReturnTrue();
    $disk->shouldReceive('size')->andReturn(4);
    Storage::set('r2_private', $disk);

    $this->artisan('assets:upload', ['paths' => ["{$this->source}/logo.png"], '--disk' => 'r2_private'])->assertSuccessful();
});

test('it fails when a file arrives in the bucket at the wrong size', function () {
    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('put')->once();
    $disk->shouldReceive('exists')->andReturnTrue();
    $disk->shouldReceive('size')->andReturn(1);
    Storage::set('r2', $disk);

    $this->artisan('assets:upload', ['paths' => ["{$this->source}/logo.png"]])
        ->expectsOutputToContain('Failed logo.png: the size in the bucket does not match')
        ->expectsOutputToContain('Uploaded 0 of 1 file to r2.')
        ->assertFailed();
});

test('it reports a file the bucket refuses and carries on with the rest', function () {
    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('put')->with('logo.png', Mockery::any(), Mockery::any())
        ->andThrow(UnableToWriteFile::atLocation('logo.png', 'Access denied'));
    $disk->shouldReceive('put')->with('disco-run/flyer-full.jpg', Mockery::any(), Mockery::any());
    $disk->shouldReceive('exists')->andReturnTrue();
    $disk->shouldReceive('size')->andReturn(5);
    Storage::set('r2', $disk);

    $this->artisan('assets:upload', ['paths' => [$this->source]])
        ->expectsOutputToContain('Access denied')
        ->expectsOutputToContain('Uploaded 1 of 2 files to r2.')
        ->assertFailed();
});

test('it refuses to run before the bucket is set', function () {
    config()->set('filesystems.disks.r2.bucket', null);

    $this->artisan('assets:upload', ['paths' => [$this->source]])
        ->expectsOutputToContain('Fill in the R2 settings')
        ->assertFailed();
});

test('it refuses any disk other than the two R2 ones', function () {
    $this->artisan('assets:upload', ['paths' => [$this->source], '--disk' => 's3'])
        ->expectsOutputToContain('must be r2 or r2_private')
        ->assertFailed();
});

test('it refuses a path that does not exist and uploads nothing', function () {
    Storage::fake('r2');

    $this->artisan('assets:upload', ['paths' => ["{$this->source}/logo.png", "{$this->source}/missing"]])
        ->expectsOutputToContain('is not a file or folder')
        ->assertFailed();

    Storage::disk('r2')->assertMissing('logo.png');
});
