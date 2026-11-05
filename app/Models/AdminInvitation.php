<?php

namespace App\Models;

use App\Enums\AdminInvitationStatus;
use App\Enums\StaffRole;
use Database\Factories\AdminInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * An invitation for someone to join the admin with the roles already chosen. They accept or decline through a
 * private link in their email. Only a fingerprint of the link's secret is stored, so nobody who can read the
 * database can use a link, and the link can't be shown again: sending again makes a new one.
 */
#[Fillable(['email', 'invited_by'])]
#[Hidden(['token'])]
class AdminInvitation extends Model
{
    /** @use HasFactory<AdminInvitationFactory> */
    use HasFactory;

    /**
     * The invitation a link's secret belongs to, if any.
     */
    public static function findByToken(string $token): ?self
    {
        return static::query()->where('token', static::fingerprint($token))->first();
    }

    /**
     * What gets stored in place of a link's secret.
     */
    public static function fingerprint(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Give the invitation a new link and a fresh expiry, and save it. Any older link stops working.
     *
     * @return string The new link's secret. This is the only time it's available, so it goes straight in the email.
     */
    public function renewLink(): string
    {
        $token = Str::random(64);

        $this->forceFill([
            'token' => static::fingerprint($token),
            'expires_at' => now()->addHours(config('lri.admin_invitations.lifetime_hours')),
        ])->save();

        return $token;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * The roles the person gets when they accept.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'admin_invitation_role');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Invitations that can still be answered.
     *
     * @param  Builder<AdminInvitation>  $query
     */
    public function scopeWaiting(Builder $query): void
    {
        $query
            ->whereNull(['accepted_at', 'declined_at', 'cancelled_at'])
            ->where('expires_at', '>', now());
    }

    public function status(): AdminInvitationStatus
    {
        return match (true) {
            $this->accepted_at !== null => AdminInvitationStatus::Accepted,
            $this->declined_at !== null => AdminInvitationStatus::Declined,
            $this->cancelled_at !== null => AdminInvitationStatus::Cancelled,
            $this->expires_at->isPast() => AdminInvitationStatus::Expired,
            default => AdminInvitationStatus::Waiting,
        };
    }

    public function isWaiting(): bool
    {
        return $this->status() === AdminInvitationStatus::Waiting;
    }

    /**
     * Whether a new link can go out. An invitation someone answered or an organiser cancelled stays closed, and one
     * whose roles have since been removed would give nothing, so those need a new invitation.
     */
    public function canBeSentAgain(): bool
    {
        return in_array($this->status(), [AdminInvitationStatus::Waiting, AdminInvitationStatus::Expired], true)
            && $this->roles->isNotEmpty();
    }

    public function grantsSuperAdmin(): bool
    {
        return $this->roles->contains('name', StaffRole::SuperAdmin->value);
    }

    /**
     * The roles as a sentence, such as "Admin and Headset desk".
     */
    public function roleNames(): string
    {
        return $this->roles->pluck('name')->join(', ', ' and ');
    }

    /**
     * Who sent the invitation, or the club itself if that person's login has since been removed.
     */
    public function inviterName(): string
    {
        return $this->invitedBy?->name ?? 'the '.config('lri.site.name').' team';
    }

    /**
     * When the link stops working, in the club's own time, such as "Friday 6 November at 9:15 AM WAT".
     */
    public function readableExpiry(): string
    {
        return $this->expires_at
            ->timezone(config('lri.admin_invitations.timezone'))
            ->format('l j F \a\t g:i A T');
    }
}
