<?php

namespace App\Models;

use Database\Factories\BarracksRegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'full_name',
    'state_of_origin',
    'lga',
    'residential_address',
    'occupation',
    'phone',
    'passport_path',
    'signature_name',
    'declared_at',
])]
class BarracksRegistration extends Model
{
    /** @use HasFactory<BarracksRegistrationFactory> */
    use HasFactory;

    /**
     * Where saved passport photographs live on the private disk.
     */
    public const PassportDirectory = 'barracks-passports';

    /**
     * Where passport photographs wait after uploading, until the form they belong to is submitted.
     */
    public const PendingPassportDirectory = 'barracks-passports/pending';

    /**
     * The shape of a pending passport's file name, so a submitted name can only point inside the pending folder.
     */
    public const PendingPassportPattern = '/^[0-9a-f-]{36}\.(jpg|jpeg|png|webp)$/';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'declared_at' => 'datetime',
        ];
    }

    /**
     * A short-lived link to the private passport photo, so only organiser pages can show it.
     */
    public function passportUrl(): string
    {
        return Storage::temporaryUrl($this->passport_path, now()->addHour());
    }
}
