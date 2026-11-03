<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where one piece of news for the email tool stands.
 */
enum EmailToolSendStatus: string implements HasColor, HasLabel
{
    case Waiting = 'waiting';
    case Sent = 'sent';
    case Skipped = 'skipped';
    case Failed = 'failed';

    /**
     * The status as organisers read it.
     */
    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Waiting',
            self::Sent => 'Sent',
            self::Skipped => 'Skipped',
            self::Failed => 'Failed',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Waiting => 'warning',
            self::Sent => 'success',
            self::Skipped => 'gray',
            self::Failed => 'danger',
        };
    }
}
