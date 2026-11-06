<?php

namespace App\Models;

use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity as BaseActivity;

/**
 * One line in the activity log: who did what, and what changed. Only super admins can read the log, and nobody can
 * change it. The person's name is saved on the line, so it still says who did it after their login has been removed.
 *
 * @property string|null $causer_name
 */
class Activity extends BaseActivity
{
    protected static function booted(): void
    {
        static::creating(function (Activity $activity): void {
            $activity->causer_name ??= $activity->causer?->name;
        });
    }

    /**
     * What changed on this line, ready to read.
     *
     * @return list<array{field: string, before: string, after: string}>
     */
    public function changeRows(): array
    {
        $before = $this->attribute_changes?->get('old') ?? [];
        $after = $this->attribute_changes?->get('attributes') ?? [];

        return collect(array_keys($before + $after))
            ->map(fn (string $attribute): array => [
                'field' => Str::of($attribute)->replace('_', ' ')->ucfirst()->toString(),
                'before' => static::readable($before[$attribute] ?? null),
                'after' => static::readable($after[$attribute] ?? null),
            ])
            ->all();
    }

    /**
     * The changes on one line, to read in the table without opening it. A new or deleted record has too many fields
     * for that, so those are left for the full view.
     */
    public function changeSummary(): ?string
    {
        if (in_array($this->event, ['created', 'deleted'], true)) {
            return null;
        }

        $summary = collect($this->changeRows())
            ->map(fn (array $change): string => "{$change['field']}: {$change['before']} → {$change['after']}")
            ->join(' · ');

        return $summary === '' ? null : Str::limit($summary, 160);
    }

    private static function readable(mixed $value): string
    {
        return match (true) {
            $value === null, $value === '' => '–',
            is_bool($value) => $value ? 'Yes' : 'No',
            is_array($value) => (string) json_encode($value),
            default => (string) $value,
        };
    }
}
