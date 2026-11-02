<?php

namespace App\Models;

use Database\Factories\BirthdayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'full_name',
    'preferred_name',
    'email',
    'phone',
    'instagram_handle',
    'tribe',
    'birth_month',
    'birth_day',
    'birth_year',
    'notes',
    'photo_paths',
    'photo_consent',
])]
class Birthday extends Model
{
    /** @use HasFactory<BirthdayFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_month' => 'integer',
            'birth_day' => 'integer',
            'birth_year' => 'integer',
            'photo_paths' => 'array',
            'photo_consent' => 'boolean',
        ];
    }

    /**
     * Limit the query to birthdays in the given month, ordered by day.
     *
     * @param  Builder<Birthday>  $query
     */
    public function scopeInMonth(Builder $query, int $month): void
    {
        $query->where('birth_month', $month)->orderBy('birth_day')->orderBy('full_name');
    }

    /**
     * The name to use when celebrating the person.
     *
     * @return Attribute<string, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): string => $this->preferred_name ?: $this->full_name);
    }

    /**
     * The date of the birthday in the given year (29 February falls on the 28th in non-leap years).
     */
    public function celebrationDate(int $year): Carbon
    {
        $day = $this->birth_month === 2 && $this->birth_day === 29 && ! Carbon::create($year)->isLeapYear()
            ? 28
            : $this->birth_day;

        return Carbon::create($year, $this->birth_month, $day);
    }

    /**
     * Short-lived links to the private photos, so only the organiser page can show them.
     *
     * @return array<int, string>
     */
    public function photoUrls(): array
    {
        return collect($this->photo_paths ?? [])
            ->map(fn (string $path): string => Storage::temporaryUrl($path, now()->addHour()))
            ->all();
    }

    /**
     * The age the person turns in the given year, when their birth year is known.
     */
    public function turningAge(int $year): ?int
    {
        return $this->birth_year ? $year - $this->birth_year : null;
    }
}
