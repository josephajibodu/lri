<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * A name someone typed. Names end up in emails sent to other people and in spreadsheets organisers open, so they can't
 * be allowed to carry links or formulas there.
 */
final class PersonName
{
    /**
     * What a name can't contain: a leading character spreadsheets read as a formula, Markdown's link brackets, or an
     * address. Plain initials such as A.B. Bello are fine, so a dot only counts when a common web ending follows it.
     */
    public const NotAllowed = '/^[=+\-@]|[\[\]]|:\/\/|\bwww\.|\.(com|net|org|ng|io|co|xyz|link|info|app|site|online|click|top|shop)\b/iu';

    public const NotAllowedMessage = 'Enter just a name, without links or symbols.';

    /**
     * The name on one line with nothing Markdown would act on, for the body of an email. Emails are written in Markdown,
     * so without this a name like [Renew your ticket](https://…) would arrive as a working link from the club.
     */
    public static function forEmail(?string $name): string
    {
        return Str::squish(preg_replace('/[\[\]`#*_~|\\\\]/u', '', (string) $name));
    }
}
