<?php

namespace App\Support;

/**
 * Addresses for the site's images and icons, which live in the Cloudflare R2 bucket rather than in the code.
 * Until R2_PUBLIC_URL is set, such as on a computer without the bucket, they are looked for under public/.
 */
final class SiteAsset
{
    /**
     * The full address of a file, given its path inside the bucket.
     */
    public static function url(string $path): string
    {
        $publicUrl = config('filesystems.disks.r2.url');

        return filled($publicUrl)
            ? rtrim($publicUrl, '/').'/'.ltrim($path, '/')
            : asset($path);
    }
}
