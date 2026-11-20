<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The places visitors come from, so one place reads the same in every report however it was noticed: a link's tags,
 * the site that sent them, or the app whose browser they opened the link in.
 */
enum TrafficChannel: string implements HasLabel
{
    case Instagram = 'instagram';
    case Facebook = 'facebook';
    case WhatsApp = 'whatsapp';
    case TikTok = 'tiktok';
    case X = 'x';
    case Snapchat = 'snapchat';
    case Telegram = 'telegram';
    case YouTube = 'youtube';
    case LinkedIn = 'linkedin';
    case Google = 'google';
    case Bing = 'bing';
    case Email = 'email';
    case Sms = 'sms';
    case Poster = 'poster';
    case Direct = 'direct';

    public function getLabel(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::TikTok => 'TikTok',
            self::X => 'X (Twitter)',
            self::YouTube => 'YouTube',
            self::LinkedIn => 'LinkedIn',
            self::Sms => 'SMS',
            self::Poster => 'Poster or flyer',
            self::Direct => 'Direct or unknown',
            default => $this->name,
        };
    }

    /**
     * The places an organiser can share a tracked link: everywhere but "direct", which is what a link doesn't say.
     *
     * @return array<string, string>
     */
    public static function linkOptions(): array
    {
        return collect(self::cases())
            ->reject(fn (self $channel): bool => $channel === self::Direct)
            ->mapWithKeys(fn (self $channel): array => [$channel->value => $channel->getLabel()])
            ->all();
    }
}
