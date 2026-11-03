<?php

use App\Filament\Resources\BarracksRegistrations\Pages\ManageBarracksRegistrations;
use App\Models\BarracksRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

/**
 * @return array<string, mixed>
 */
function validBarracksRegistrationData(array $overrides = []): array
{
    return [
        'full_name' => 'Ada Okafor',
        'state_of_origin' => 'Oyo',
        'lga' => 'Ibadan North',
        'residential_address' => '12 Akobo Road, Ibadan',
        'occupation' => 'Software engineer',
        'phone' => '0803 123 4567',
        'passport' => UploadedFile::fake()->image('passport.jpg'),
        'signature_name' => 'Ada Okafor',
        'declaration' => '1',
        ...$overrides,
    ];
}

test('anyone can open the barracks registration form', function () {
    $this->get(route('barracks.create'))
        ->assertOk()
        ->assertSee('Akobo Barracks registration')
        ->assertSee('Adekunle Fajuyi Cantonment')
        ->assertSee('₦2,000');
});

test('a registration is saved with its passport stored privately', function () {
    $this->post(route('barracks.store'), validBarracksRegistrationData())
        ->assertRedirect(route('barracks.create'))
        ->assertSessionHas('status');

    $registration = BarracksRegistration::sole();

    expect($registration)
        ->full_name->toBe('Ada Okafor')
        ->state_of_origin->toBe('Oyo')
        ->lga->toBe('Ibadan North')
        ->phone->toBe('0803 123 4567')
        ->signature_name->toBe('Ada Okafor')
        ->declared_at->not->toBeNull();

    Storage::disk('local')->assertExists($registration->passport_path);
});

test('every field on the permit is required', function (string $field) {
    $this->post(route('barracks.store'), validBarracksRegistrationData([$field => null]))
        ->assertSessionHasErrors($field);

    expect(BarracksRegistration::count())->toBe(0);
})->with([
    'full_name', 'state_of_origin', 'lga', 'residential_address', 'occupation', 'phone', 'passport', 'signature_name', 'declaration',
]);

test('the passport must be an image of 5 MB or less', function (UploadedFile $passport) {
    $this->post(route('barracks.store'), validBarracksRegistrationData(['passport' => $passport]))
        ->assertSessionHasErrors('passport');
})->with([
    'not an image' => fn () => UploadedFile::fake()->create('passport.pdf', 10, 'application/pdf'),
    'too large' => fn () => UploadedFile::fake()->image('passport.jpg')->size(6000),
]);

test('a passport uploaded ahead of the form is moved into place when the form is submitted', function () {
    $upload = $this->postJson(route('barracks.passport'), ['passport' => UploadedFile::fake()->image('passport.png')])
        ->assertOk()
        ->assertJsonStructure(['passport_upload', 'preview_url']);

    $pendingPath = BarracksRegistration::PendingPassportDirectory.'/'.$upload->json('passport_upload');
    Storage::disk('local')->assertExists($pendingPath);

    $this->post(route('barracks.store'), validBarracksRegistrationData([
        'passport' => null,
        'passport_upload' => $upload->json('passport_upload'),
    ]))->assertSessionHasNoErrors();

    $registration = BarracksRegistration::sole();

    expect($registration->passport_path)->toBe(BarracksRegistration::PassportDirectory.'/'.$upload->json('passport_upload'));
    Storage::disk('local')->assertExists($registration->passport_path);
    Storage::disk('local')->assertMissing($pendingPath);
});

test('the passport upload rejects files that are not images', function () {
    $this->postJson(route('barracks.passport'), ['passport' => UploadedFile::fake()->create('passport.pdf', 10, 'application/pdf')])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('passport');
});

test('a passport upload that does not exist or points elsewhere is rejected', function (string $passportUpload) {
    $this->post(route('barracks.store'), validBarracksRegistrationData([
        'passport' => null,
        'passport_upload' => $passportUpload,
    ]))->assertSessionHasErrors('passport_upload');

    expect(BarracksRegistration::count())->toBe(0);
})->with([
    'never uploaded' => '6f1c2d3e-4b5a-4c6d-8e7f-9a0b1c2d3e4f.jpg',
    'outside the pending folder' => '../../.env',
]);

test('the form keeps an uploaded passport when other fields need fixing', function () {
    $upload = $this->postJson(route('barracks.passport'), ['passport' => UploadedFile::fake()->image('passport.jpg')]);

    $this->from(route('barracks.create'))
        ->post(route('barracks.store'), validBarracksRegistrationData([
            'full_name' => null,
            'passport' => null,
            'passport_upload' => $upload->json('passport_upload'),
        ]))
        ->assertRedirect(route('barracks.create'));

    $this->get(route('barracks.create'))
        ->assertSee('value="'.$upload->json('passport_upload').'"', false)
        ->assertSee('Photo uploaded.');
});

test('passports uploaded over a day ago without a form are pruned', function () {
    Storage::disk('local')->put(BarracksRegistration::PendingPassportDirectory.'/old.jpg', 'x');
    Storage::disk('local')->put(BarracksRegistration::PendingPassportDirectory.'/new.jpg', 'x');

    touch(Storage::disk('local')->path(BarracksRegistration::PendingPassportDirectory.'/old.jpg'), now()->subDays(2)->getTimestamp());

    $this->artisan('barracks:prune-passports')->assertSuccessful();

    Storage::disk('local')->assertMissing(BarracksRegistration::PendingPassportDirectory.'/old.jpg');
    Storage::disk('local')->assertExists(BarracksRegistration::PendingPassportDirectory.'/new.jpg');
});

test('the same phone number cannot register twice', function () {
    BarracksRegistration::factory()->create(['phone' => '0803 123 4567']);

    $this->post(route('barracks.store'), validBarracksRegistrationData())
        ->assertSessionHasErrors('phone');
});

test('the registration list and print pages are behind the organiser sign-in', function () {
    $this->get('/admin/barracks-registrations')->assertRedirect('/admin/login');
    $this->get(route('barracks.print'))->assertRedirect('/admin/login');
    $this->get('/akobo/barracks/registrations')->assertRedirect('/admin/barracks-registrations');
});

test('organisers see everyone who registered, oldest first', function () {
    $first = BarracksRegistration::factory()->create(['created_at' => now()->subDay()]);
    $second = BarracksRegistration::factory()->create();

    $this->actingAs(User::factory()->create());

    Livewire::test(ManageBarracksRegistrations::class)
        ->assertCanSeeTableRecords([$first, $second], inOrder: true)
        ->assertTableActionHasUrl('print', route('barracks.print', $first), $first);
});

test('organisers can print every permit form or just one', function () {
    $ada = BarracksRegistration::factory()->create(['full_name' => 'Ada Okafor']);
    BarracksRegistration::factory()->create(['full_name' => 'Tunde Bello']);

    $organiser = User::factory()->create();

    $this->actingAs($organiser)
        ->get(route('barracks.print'))
        ->assertOk()
        ->assertSee('PERMIT')
        ->assertSee('Sports Centre')
        ->assertSee('Ada Okafor')
        ->assertSee('Tunde Bello');

    $this->actingAs($organiser)
        ->get(route('barracks.print', $ada))
        ->assertOk()
        ->assertSee('Ada Okafor')
        ->assertDontSee('Tunde Bello');
});
