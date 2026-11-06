<?php

namespace App\Models\Concerns;

use App\Models\Activity;
use App\Support\Money;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Puts every change a signed-in admin makes to this model on the activity log. Changes with nobody signed in, such as
 * a buyer checking out or a payment provider's webhook, are left out: they aren't what an admin did, and they'd bury
 * what is.
 *
 * Only the attributes a model lists are recorded, so a secret added to its table later can't end up in the log.
 */
trait RecordsAdminActivity
{
    use LogsActivity {
        shouldLogEvent as protected shouldLogEventOnceSignedIn;
    }

    /**
     * How the log names this record, such as "coupon LRI20".
     */
    abstract public function activityLabel(): string;

    /**
     * The attributes the log may record.
     *
     * @return list<string>
     */
    abstract protected function activityAttributes(): array;

    /**
     * The recorded attributes that are amounts of money, stored in the currency's smallest unit.
     *
     * @return list<string>
     */
    protected function activityMoneyAttributes(): array
    {
        return [];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->activityAttributes())
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return match ($eventName) {
            'created' => 'Created '.$this->activityLabel(),
            'deleted' => 'Deleted '.$this->activityLabel(),
            default => 'Changed '.$this->activityLabel(),
        };
    }

    protected function shouldLogEvent(string $eventName): bool
    {
        return auth()->check() && $this->shouldLogEventOnceSignedIn($eventName);
    }

    /**
     * Save the changes as people read them, so the log stays right even if the record is later changed or removed.
     */
    public function beforeActivityLogged(Activity $activity, string $eventName): void
    {
        $activity->attribute_changes = $activity->attribute_changes?->map(
            fn (array $values): array => collect($values)
                ->map(fn (mixed $value, string $attribute): mixed => $this->readableActivityValue($attribute, $value))
                ->all(),
        );
    }

    private function readableActivityValue(string $attribute, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (in_array($attribute, $this->activityMoneyAttributes(), true)) {
            return Money::of((int) $value, $this->currency)->format();
        }

        if ($this->isDateAttribute($attribute)) {
            return Carbon::parse($value)->timezone('Africa/Lagos')->format('j M Y, g:i A');
        }

        if ($this->isEnumCastable($attribute)) {
            $case = $this->getCasts()[$attribute]::tryFrom($value);

            return match (true) {
                $case instanceof HasLabel => $case->getLabel(),
                $case !== null && method_exists($case, 'label') => $case->label(),
                default => $value,
            };
        }

        return $value;
    }
}
