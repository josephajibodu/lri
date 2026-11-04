<?php

use App\Filament\Resources\Birthdays\Pages\ManageBirthdays;
use App\Models\Birthday;
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
function validBirthdayData(array $overrides = []): array
{
    return [
        'full_name' => 'Ada Okafor',
        'preferred_name' => 'Ada',
        'email' => 'ada@example.com',
        'phone' => '+234 800 000 0000',
        'instagram_handle' => '@ada.runs',
        'tribe' => 'Bodija',
        'birth_month' => 11,
        'birth_day' => 14,
        'birth_year' => 1994,
        'notes' => 'Turning 30! Happy for you to mention it.',
        'photos' => [UploadedFile::fake()->image('ada.jpg')],
        'photo_consent' => '1',
        ...$overrides,
    ];
}

test('anyone can open the share birthday form', function () {
    $this->get(route('birthdays.create'))
        ->assertOk()
        ->assertSee('Share your birthday')
        ->assertSee('Oluyole / Akala');
});

test('a birthday is saved with its photos stored privately', function () {
    $this->post(route('birthdays.store'), validBirthdayData([
        'photos' => [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.png')],
    ]))
        ->assertRedirect(route('birthdays.create'))
        ->assertSessionHas('status');

    $birthday = Birthday::sole();

    expect($birthday)
        ->full_name->toBe('Ada Okafor')
        ->instagram_handle->toBe('ada.runs')
        ->tribe->toBe('Bodija')
        ->notes->toBe('Turning 30! Happy for you to mention it.')
        ->birth_month->toBe(11)
        ->birth_day->toBe(14)
        ->photo_consent->toBeTrue()
        ->photo_paths->toHaveCount(2);

    foreach ($birthday->photo_paths as $photoPath) {
        Storage::disk('local')->assertExists($photoPath);
    }
});

test('a day that does not exist in the month is rejected', function () {
    $this->post(route('birthdays.store'), validBirthdayData(['birth_month' => 4, 'birth_day' => 31, 'birth_year' => null]))
        ->assertSessionHasErrors('birth_day');

    expect(Birthday::count())->toBe(0);
});

test('29 February is accepted when no birth year is given', function () {
    $this->post(route('birthdays.store'), validBirthdayData(['birth_month' => 2, 'birth_day' => 29, 'birth_year' => null]))
        ->assertSessionHasNoErrors();
});

test('uploads must be images within the photo limit', function (array $photos, string $errorKey) {
    $this->post(route('birthdays.store'), validBirthdayData(['photos' => $photos]))
        ->assertSessionHasErrors($errorKey);
})->with([
    'no photos' => fn () => [[], 'photos'],
    'too many photos' => fn () => [array_map(fn () => UploadedFile::fake()->image('p.jpg'), range(1, 4)), 'photos'],
    'not an image' => fn () => [[UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')], 'photos.0'],
    'too large' => fn () => [[UploadedFile::fake()->image('big.jpg')->size(6000)], 'photos.0'],
]);

test('the tribe must be one of the club tribes', function () {
    $this->post(route('birthdays.store'), validBirthdayData(['tribe' => 'Lagos']))
        ->assertSessionHasErrors('tribe');
});

test('photo consent and a unique email are required', function () {
    Birthday::factory()->create(['email' => 'ada@example.com']);

    $this->post(route('birthdays.store'), validBirthdayData(['photo_consent' => null]))
        ->assertSessionHasErrors(['email', 'photo_consent']);
});

test('the birthday list is in the admin, behind the organiser sign-in', function () {
    $this->get('/admin/birthdays')->assertRedirect('/admin/login');
    $this->get('/birthdays')->assertRedirect('/admin/birthdays');
});

test('the birthday list shows next month by default, in date order', function () {
    $this->travelTo(now()->setDate(2026, 10, 26));

    $later = Birthday::factory()->on(11, 20)->create(['full_name' => 'Later Person']);
    $early = Birthday::factory()->on(11, 3)->create(['full_name' => 'Early Person', 'notes' => 'Big 4-0, please mention it!']);
    $december = Birthday::factory()->on(12, 1)->create(['full_name' => 'December Person']);

    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ManageBirthdays::class)
        ->assertCanSeeTableRecords([$early, $later], inOrder: true)
        ->assertCanNotSeeTableRecords([$december])
        ->assertSeeText('Big 4-0, please mention it!');
});

test('the birthday list can show another month, with the age turned at the next celebration', function () {
    $this->travelTo(now()->setDate(2026, 10, 26));

    $january = Birthday::factory()->on(1, 5)->create(['birth_year' => 1990]);

    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ManageBirthdays::class)
        ->filterTable('birth_month', 1)
        ->assertCanSeeTableRecords([$january])
        ->assertTableColumnStateSet('turning', '37', $january);
});
