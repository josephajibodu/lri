<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Where a tracked link sends people. Only the site's own pages, so a short link can never be used to send someone
 * somewhere else.
 */
enum TrackedLinkDestination: string implements HasLabel
{
    case TicketPage = 'ticket_page';
    case TicketForm = 'ticket_form';
    case HomePage = 'home_page';

    public function getLabel(): string
    {
        return match ($this) {
            self::TicketPage => 'The top of the ticket page',
            self::TicketForm => 'Straight to the ticket form',
            self::HomePage => 'The home page',
        };
    }

    /**
     * The page's address, carrying the given link tags.
     *
     * @param  array<string, string>  $tags
     */
    public function url(array $tags): string
    {
        return match ($this) {
            self::TicketPage => route('disco-run', $tags),
            self::TicketForm => route('disco-run', $tags).'#tickets',
            self::HomePage => route('home', $tags),
        };
    }
}
