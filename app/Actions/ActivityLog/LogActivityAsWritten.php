<?php

namespace App\Actions\ActivityLog;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Actions\LogActivityAction;

/**
 * Saves each line of the activity log exactly as written. The package normally swaps text such as ":subject.password"
 * for that value from the record, and our lines include names people type, so a name could otherwise pull a secret
 * into the log.
 */
class LogActivityAsWritten extends LogActivityAction
{
    protected function replacePlaceholders(string $description, Model $activity): string
    {
        return $description;
    }
}
