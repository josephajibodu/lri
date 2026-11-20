<?php

namespace App\Support;

use App\Enums\TrafficChannel;

/**
 * Work out where a visitor came from, and name it the same way everywhere.
 *
 * A visit can say where it came from in four ways, from most to least reliable: the tags on the link (utm_source and
 * the rest, which tracked links add), an ad network's click id, the app whose built-in browser opened the link, and
 * the site that sent them. WhatsApp, Telegram and typed or scanned addresses usually say nothing at all, which is why
 * those visits read as "direct" unless the link carried tags.
 */
final class TrafficSource
{
    /**
     * Sites, and Android apps that send their package name instead of a site, by the place they belong to. A host
     * matches when it is the domain or ends with it, so www.instagram.com and l.instagram.com both count. Email comes
     * before Google, so Gmail isn't read as a search.
     */
    private const Hosts = [
        'email' => ['mail.google.com', 'com.google.android.gm', 'outlook.live.com', 'outlook.office.com', 'mail.yahoo.com'],
        'instagram' => ['instagram.com', 'com.instagram.android'],
        'facebook' => ['facebook.com', 'fb.com', 'fb.me', 'messenger.com', 'com.facebook.katana', 'com.facebook.orca'],
        'whatsapp' => ['whatsapp.com', 'whatsapp.net', 'wa.me', 'com.whatsapp', 'com.whatsapp.w4b'],
        'tiktok' => ['tiktok.com', 'com.zhiliaoapp.musically', 'com.ss.android.ugc.trill'],
        'x' => ['t.co', 'twitter.com', 'x.com', 'com.twitter.android'],
        'snapchat' => ['snapchat.com', 'com.snapchat.android'],
        'telegram' => ['t.me', 'telegram.me', 'telegram.org', 'org.telegram.messenger'],
        'youtube' => ['youtube.com', 'youtu.be', 'com.google.android.youtube'],
        'linkedin' => ['linkedin.com', 'lnkd.in', 'com.linkedin.android'],
        'bing' => ['bing.com'],
        'google' => ['com.google.android.googlequicksearchbox', 'com.google.android.gm.lite'],
    ];

    /**
     * Apps whose built-in browser names itself, which tells us where the link was tapped even when the app hides the
     * site it came from. Instagram comes first because its browser is built on Facebook's.
     */
    private const Apps = [
        'instagram' => '/\bInstagram\b/i',
        'facebook' => '/FBAN|FBAV|FB_IAB|FBIOS/i',
        'tiktok' => '/musical_ly|BytedanceWebview|\bTikTok\b/i',
        'snapchat' => '/\bSnapchat\b/i',
        'linkedin' => '/LinkedInApp/i',
        'x' => '/\bTwitter\b/i',
    ];

    /**
     * Click ids that ad networks add only to their ads, so they mean a paid click.
     */
    private const PaidClickIds = [
        'gclid' => 'google',
        'gbraid' => 'google',
        'wbraid' => 'google',
        'msclkid' => 'bing',
        'ttclid' => 'tiktok',
        'twclid' => 'x',
    ];

    /**
     * Short names people type in their own link tags, read as the place they mean.
     */
    private const Aliases = [
        'ig' => 'instagram',
        'insta' => 'instagram',
        'fb' => 'facebook',
        'wa' => 'whatsapp',
        'twitter' => 'x',
        'tt' => 'tiktok',
        'yt' => 'youtube',
        'mail' => 'email',
        'newsletter' => 'email',
        'qr' => 'poster',
        'flyer' => 'poster',
    ];

    /**
     * Crawlers and link previews (WhatsApp, Instagram and the like), which would otherwise count as visits.
     */
    public static function isCrawler(?string $userAgent): bool
    {
        return (bool) preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|discord|curl|wget|python|headless/i', (string) $userAgent);
    }

    /**
     * The place a site or Android app belongs to, if it's one we know.
     */
    public static function fromHost(?string $host): ?TrafficChannel
    {
        $host = strtolower(trim((string) $host));

        if ($host === '') {
            return null;
        }

        foreach (self::Hosts as $channel => $domains) {
            foreach ($domains as $domain) {
                if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                    return TrafficChannel::from($channel);
                }
            }
        }

        // Google searches arrive from google.com, google.com.ng, google.co.uk and the rest.
        return preg_match('/(^|\.)google\.[a-z]{2,3}(\.[a-z]{2})?$/', $host) ? TrafficChannel::Google : null;
    }

    /**
     * The app whose built-in browser this is, if it says.
     */
    public static function fromUserAgent(?string $userAgent): ?TrafficChannel
    {
        foreach (self::Apps as $channel => $pattern) {
            if (preg_match($pattern, (string) $userAgent)) {
                return TrafficChannel::from($channel);
            }
        }

        return null;
    }

    /**
     * Where an ad network's click id says the visit came from. Meta adds fbclid to every link tapped in Facebook or
     * Instagram, paid or not, so it only names the place, using the app or site the visit came through to tell the
     * two apart.
     *
     * @param  array<string, mixed>  $query
     * @return array{source: string, medium?: string}|null
     */
    public static function fromClickIds(array $query, ?TrafficChannel $cameThrough): ?array
    {
        foreach (self::PaidClickIds as $parameter => $channel) {
            if (filled($query[$parameter] ?? null)) {
                return ['source' => $channel, 'medium' => 'paid'];
            }
        }

        if (filled($query['fbclid'] ?? null)) {
            return ['source' => $cameThrough === TrafficChannel::Instagram ? TrafficChannel::Instagram->value : TrafficChannel::Facebook->value];
        }

        return null;
    }

    /**
     * Where a visit or order came from, from what was remembered about it: a known place's key, like "instagram",
     * whatever else a link's tags or a site said, or "direct" when nothing did.
     *
     * Orders from before Instagram was told apart from Facebook carry source "facebook" with medium "paid" for any
     * fbclid. When the site that sent them was Instagram, that's where they came from.
     *
     * @param  array<string, mixed>|null  $attribution
     */
    public static function of(?array $attribution): string
    {
        $source = self::clean($attribution['source'] ?? null);
        $referrer = self::clean($attribution['referrer'] ?? null);
        $sentBy = self::fromHost($referrer);

        if ($source === 'facebook' && ($attribution['medium'] ?? null) === 'paid' && $sentBy === TrafficChannel::Instagram) {
            return TrafficChannel::Instagram->value;
        }

        if ($source !== null) {
            return self::fromHost($source)?->value ?? self::Aliases[$source] ?? $source;
        }

        if ($referrer !== null) {
            return $sentBy?->value ?? $referrer;
        }

        return TrafficChannel::Direct->value;
    }

    /**
     * How a source reads in reports: the place's name when we know it, or the tag or site as it was.
     */
    public static function label(string $source): string
    {
        return TrafficChannel::tryFrom($source)?->getLabel() ?? $source;
    }

    private static function clean(mixed $value): ?string
    {
        $value = is_string($value) ? strtolower(trim($value)) : '';

        return $value === '' ? null : $value;
    }
}
