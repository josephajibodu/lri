<?php

use App\Models\Birthday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

test('the birthday list requires signing in', function () {
    $this->get(route('birthdays.index'))->assertUnauthorized();
});

test('the birthday list shows next month by default, in date order', function () {
    $this->travelTo(now()->setDate(2026, 10, 26));

    Birthday::factory()->on(11, 20)->create(['full_name' => 'Later Person', 'preferred_name' => null]);
    Birthday::factory()->on(11, 3)->create(['full_name' => 'Early Person', 'preferred_name' => null, 'notes' => 'Big 4-0, please mention it!']);
    Birthday::factory()->on(12, 1)->create(['full_name' => 'December Person', 'preferred_name' => null]);

    $this->actingAs(User::factory()->create())
        ->get(route('birthdays.index'))
        ->assertOk()
        ->assertSeeText('November 2026')
        ->assertSeeInOrder(['Early Person', 'Later Person'])
        ->assertSeeText('Big 4-0, please mention it!')
        ->assertDontSee('December Person');
});

test('the birthday list can show another month', function () {
    $this->travelTo(now()->setDate(2026, 10, 26));

    Birthday::factory()->on(1, 5)->create(['full_name' => 'January Person', 'preferred_name' => null]);

    $this->actingAs(User::factory()->create())
        ->get(route('birthdays.index', ['month' => 1]))
        ->assertOk()
        ->assertSeeText('January 2027')
        ->assertSee('January Person');
});
