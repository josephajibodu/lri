<?php

use App\Enums\AdminPermission;
use App\Enums\TrackedLinkDestination;
use App\Enums\TrafficChannel;
use App\Filament\Resources\TrackedLinks\Pages\ManageTrackedLinks;
use App\Models\CheckoutEvent;
use App\Models\TrackedLink;
use App\Models\User;
use App\Reports\ReportPeriod;
use App\Reports\SalesBySource;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

test('a tracked link counts the tap and sends the visitor on with its tags', function () {
    $this->freezeTime();
    $link = TrackedLink::factory()->create(['slug' => 'wa-group', 'source' => TrafficChannel::WhatsApp, 'medium' => 'group', 'campaign' => 'black-friday']);

    $this->get('/go/wa-group')
        ->assertRedirect(route('disco-run', ['utm_source' => 'whatsapp', 'utm_medium' => 'group', 'utm_campaign' => 'black-friday', 'utm_content' => 'wa-group']));

    $link->refresh();

    expect($link->clicks)->toBe(1)
        ->and($link->last_clicked_at->toDateTimeString())->toBe(now()->toDateTimeString());
});

test('a tracked link can go to the ticket form or the home page', function (TrackedLinkDestination $destination, string $page, string $anchor) {
    TrackedLink::factory()->create(['slug' => 'ig-bio', 'destination' => $destination]);

    $this->get('/go/ig-bio')->assertRedirect(route($page, ['utm_source' => 'instagram', 'utm_medium' => 'bio', 'utm_content' => 'ig-bio']).$anchor);
})->with([
    'the ticket form' => [TrackedLinkDestination::TicketForm, 'disco-run', '#tickets'],
    'the home page' => [TrackedLinkDestination::HomePage, 'home', ''],
]);

test('link previews and crawlers are sent on without counting as taps', function () {
    $link = TrackedLink::factory()->create(['slug' => 'ig-bio']);

    $this->withHeader('User-Agent', 'WhatsApp/2.24.1.6 A')->get('/go/ig-bio')->assertRedirect();

    expect($link->fresh()->clicks)->toBe(0);
});

test('a tap isn’t an edit, so it stays out of the activity log', function () {
    $link = TrackedLink::factory()->create(['slug' => 'ig-bio']);
    $this->actingAs(User::factory()->superAdmin()->create());

    $this->get('/go/ig-bio');

    expect(Activity::query()->where('subject_type', $link->getMorphClass())->where('event', 'updated')->exists())->toBeFalse();
});

test('an address that doesn’t exist still lands on the tickets', function () {
    $this->get('/go/no-such-link')->assertRedirect(route('disco-run'));
});

test('visitors and orders through a tracked link are credited to it by name on the dashboard', function () {
    TrackedLink::factory()->create(['name' => 'Running club WhatsApp', 'slug' => 'club-wa', 'source' => TrafficChannel::WhatsApp, 'medium' => 'group']);

    $target = $this->get('/go/club-wa')->headers->get('Location');
    $this->get($target)->assertOk();

    $row = collect(SalesBySource::for(new ReportPeriod))->sole();

    expect(CheckoutEvent::query()->sole()->properties)->toMatchArray(['source' => 'whatsapp', 'link' => 'club-wa'])
        ->and($row)->toMatchArray(['source' => 'WhatsApp', 'detail' => 'Running club WhatsApp', 'visitors' => 1]);
});

test('organisers make a link with a short address from its name, and it records who made it', function () {
    $organiser = User::factory()->withPermissions(AdminPermission::ManageLinks)->create();
    $this->actingAs($organiser);

    Livewire::test(ManageTrackedLinks::class)
        ->mountAction('create')
        ->fillForm(['name' => 'Ada’s Instagram story'])
        ->assertSchemaStateSet(['slug' => 'adas-instagram-story'])
        ->fillForm(['source' => TrafficChannel::Instagram->value, 'medium' => 'Story', 'campaign' => 'Early Bird'])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(TrackedLink::query()->sole())
        ->slug->toBe('adas-instagram-story')
        ->source->toBe(TrafficChannel::Instagram)
        ->medium->toBe('story')
        ->campaign->toBe('early-bird')
        ->destination->toBe(TrackedLinkDestination::TicketPage)
        ->created_by->toBe($organiser->id);
});

test('a short address must be unique and easy to type', function (string $slug, string $message) {
    TrackedLink::factory()->create(['slug' => 'ig-bio']);
    $this->actingAs(User::factory()->withPermissions(AdminPermission::ManageLinks)->create());

    Livewire::test(ManageTrackedLinks::class)
        ->callAction('create', data: ['name' => 'Bio', 'slug' => $slug, 'source' => TrafficChannel::Instagram->value])
        ->assertHasActionErrors(['slug' => $message]);

    expect(TrackedLink::query()->count())->toBe(1);
})->with([
    'taken' => ['ig-bio', 'Another link already uses this address.'],
    'spaces and capitals' => ['IG Bio', 'Use lowercase letters, numbers and single dashes, like ig-bio.'],
]);

test('a link’s address can’t change once it’s made, but the rest can', function () {
    $link = TrackedLink::factory()->create(['slug' => 'ig-bio', 'name' => 'Bio']);
    $this->actingAs(User::factory()->withPermissions(AdminPermission::ManageLinks)->create());

    Livewire::test(ManageTrackedLinks::class)
        ->callAction(TestAction::make('edit')->table($link), data: ['name' => 'Instagram bio', 'slug' => 'changed'])
        ->assertHasNoActionErrors();

    expect($link->fresh())->name->toBe('Instagram bio')->slug->toBe('ig-bio');
});

test('a link can only be deleted before anyone has tapped it', function () {
    $untapped = TrackedLink::factory()->create();
    $tapped = TrackedLink::factory()->clicked(3)->create();
    $this->actingAs(User::factory()->withPermissions(AdminPermission::ManageLinks)->create());

    Livewire::test(ManageTrackedLinks::class)
        ->assertActionHidden(TestAction::make('delete')->table($tapped))
        ->callAction(TestAction::make('delete')->table($untapped));

    $this->assertModelMissing($untapped);
    $this->assertModelExists($tapped);
});

test('a link’s QR code opens for printing', function () {
    $link = TrackedLink::factory()->create(['slug' => 'poster-ui']);
    $this->actingAs(User::factory()->withPermissions(AdminPermission::ManageLinks)->create());

    Livewire::test(ManageTrackedLinks::class)
        ->mountAction(TestAction::make('qrCode')->table($link))
        ->assertMountedActionModalSee(['Download for printing', Str::after(url('/go/poster-ui'), '://')])
        ->assertMountedActionModalSeeHtml('download="lri-poster-ui-qr-code.png"');
});
