<?php

use App\Console\Commands\ImportSignupsFromSqlite;
use App\Models\BarracksRegistration;
use App\Models\Birthday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->sqlitePath = tempnam(sys_get_temp_dir(), 'lri-signups-');

    config(['database.connections.'.ImportSignupsFromSqlite::SourceConnection => [
        'driver' => 'sqlite',
        'database' => $this->sqlitePath,
        'prefix' => '',
    ]]);
    $this->artisan('migrate', ['--database' => ImportSignupsFromSqlite::SourceConnection, '--force' => true]);

    $this->oldDatabase = DB::connection(ImportSignupsFromSqlite::SourceConnection);
});

afterEach(function () {
    DB::purge(ImportSignupsFromSqlite::SourceConnection);
    @unlink($this->sqlitePath);
});

test('sign-ups and organiser logins are copied across with their own dates, photos and passwords', function () {
    $this->oldDatabase->table('users')->insert([
        'name' => 'Organiser', 'email' => 'organiser@example.com', 'password' => '$2y$12$existinghash',
        'created_at' => '2026-10-20 09:00:00', 'updated_at' => '2026-10-20 09:00:00',
    ]);
    $this->oldDatabase->table('birthdays')->insert([
        'full_name' => 'Ada Okafor', 'email' => 'ada@example.com', 'birth_month' => 11, 'birth_day' => 14,
        'photo_paths' => '["birthday-photos/ada.jpg"]', 'photo_consent' => 1,
        'created_at' => '2026-10-27 08:15:00', 'updated_at' => '2026-10-27 08:15:00',
    ]);
    $this->oldDatabase->table('barracks_registrations')->insert([
        'full_name' => 'Tunde Bello', 'state_of_origin' => 'Oyo', 'lga' => 'Ibadan North', 'residential_address' => '12 Akobo Road',
        'occupation' => 'Teacher', 'phone' => '0803 123 4567', 'passport_path' => 'barracks-passports/tunde.jpg',
        'signature_name' => 'Tunde Bello', 'declared_at' => '2026-10-28 18:00:00',
        'created_at' => '2026-10-28 18:00:00', 'updated_at' => '2026-10-28 18:00:00',
    ]);

    $this->artisan('signups:import-sqlite', ['path' => $this->sqlitePath])->assertSuccessful();

    expect(User::sole())->email->toBe('organiser@example.com')->password->toBe('$2y$12$existinghash')
        ->and(Birthday::sole())->photo_paths->toBe(['birthday-photos/ada.jpg'])->created_at->toDateTimeString()->toBe('2026-10-27 08:15:00')
        ->and(BarracksRegistration::sole())->passport_path->toBe('barracks-passports/tunde.jpg')->declared_at->toDateTimeString()->toBe('2026-10-28 18:00:00');
});

test('people already in the new database are left alone, so the copy can be run again', function () {
    Birthday::factory()->create(['email' => 'ada@example.com', 'full_name' => 'Ada (already here)']);
    $this->oldDatabase->table('birthdays')->insert([
        ['full_name' => 'Ada Okafor', 'email' => 'ada@example.com', 'birth_month' => 11, 'birth_day' => 14, 'photo_consent' => 1],
        ['full_name' => 'Bisi Ade', 'email' => 'bisi@example.com', 'birth_month' => 3, 'birth_day' => 2, 'photo_consent' => 1],
    ]);

    $this->artisan('signups:import-sqlite', ['path' => $this->sqlitePath])->assertSuccessful();
    $this->artisan('signups:import-sqlite', ['path' => $this->sqlitePath])->assertSuccessful();

    expect(Birthday::query()->orderBy('email')->pluck('full_name')->all())->toBe(['Ada (already here)', 'Bisi Ade']);
});

test('a dry run counts what would be copied without saving it', function () {
    $this->oldDatabase->table('birthdays')->insert([
        'full_name' => 'Ada Okafor', 'email' => 'ada@example.com', 'birth_month' => 11, 'birth_day' => 14, 'photo_consent' => 1,
    ]);

    $this->artisan('signups:import-sqlite', ['path' => $this->sqlitePath, '--dry-run' => true])
        ->expectsOutputToContain('Dry run: nothing was saved.')
        ->assertSuccessful();

    expect(Birthday::count())->toBe(0);
});

test('the copy stops when the SQLite file is missing', function () {
    $this->artisan('signups:import-sqlite', ['path' => '/nowhere/database.sqlite'])->assertFailed();
});
