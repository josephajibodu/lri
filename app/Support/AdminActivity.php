<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Put something on the activity log by hand, for what an admin does that isn't a plain change to a model: emailing
 * tickets, downloading orders, or changing who has which role. Other changes are recorded by RecordsAdminActivity.
 */
final class AdminActivity
{
    /**
     * Record something done by whoever is signed in. With nobody signed in, nothing is recorded.
     */
    public static function record(string $description, ?Model $subject = null): void
    {
        $person = auth()->user();

        if ($person === null) {
            return;
        }

        activity()
            ->causedBy($person)
            ->when($subject, fn ($logger) => $logger->performedOn($subject))
            ->log($description);
    }

    /**
     * Record a change, keeping only the fields that differ. If none do, nothing is recorded.
     *
     * @param  array<string, string|null>  $before
     * @param  array<string, string|null>  $after
     * @param  User|null  $by  Who did it, when they aren't signed in yet, such as someone accepting an invitation.
     */
    public static function recordChange(string $description, Model $subject, array $before, array $after, ?User $by = null): void
    {
        $person = $by ?? auth()->user();
        $old = [];
        $new = [];

        foreach (array_keys($before + $after) as $field) {
            if (($before[$field] ?? '') !== ($after[$field] ?? '')) {
                $old[$field] = $before[$field] ?? null;
                $new[$field] = $after[$field] ?? null;
            }
        }

        if ($person === null || $new === []) {
            return;
        }

        activity()
            ->causedBy($person)
            ->performedOn($subject)
            ->withChanges(['old' => $old, 'attributes' => $new])
            ->log($description);
    }
}
