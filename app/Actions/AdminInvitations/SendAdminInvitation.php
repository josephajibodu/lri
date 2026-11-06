<?php

namespace App\Actions\AdminInvitations;

use App\Exceptions\AdminInvitationNotUsable;
use App\Mail\AdminInvitationMail;
use App\Models\AdminInvitation;
use App\Models\User;
use App\Support\AdminActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Invite someone to the admin with the roles they'll get, or send a waiting or expired invitation again.
 */
class SendAdminInvitation
{
    /**
     * @param  array<int, int|string>  $roleIds
     *
     * @throws AdminInvitationNotUsable
     */
    public function handle(string $email, array $roleIds, User $invitedBy): AdminInvitation
    {
        $email = Str::lower(trim($email));

        $this->ensureCanBeInvited($email);

        [$invitation, $token] = DB::transaction(function () use ($email, $roleIds, $invitedBy): array {
            $invitation = new AdminInvitation(['email' => $email, 'invited_by' => $invitedBy->id]);
            $token = $invitation->renewLink();
            $invitation->roles()->sync($roleIds);

            AdminActivity::recordChange(
                "Invited {$email} to the admin",
                $invitation,
                before: [],
                after: ['roles' => $invitation->roles()->orderBy('name')->pluck('name')->join(', ')],
                by: $invitedBy,
            );

            return [$invitation, $token];
        });

        $this->email($invitation, $token);

        return $invitation;
    }

    /**
     * Send a new link with a fresh expiry. The link in the earlier email stops working.
     *
     * @throws AdminInvitationNotUsable
     */
    public function again(AdminInvitation $invitation): AdminInvitation
    {
        $this->ensureCanBeInvited($invitation->email, except: $invitation);

        $this->email($invitation, $invitation->renewLink());

        AdminActivity::record("Sent {$invitation->email} a new invitation link", $invitation);

        return $invitation;
    }

    /**
     * Someone with a login changes roles under People, and one open invitation per address keeps it clear which
     * link and roles count.
     *
     * @throws AdminInvitationNotUsable
     */
    public function ensureCanBeInvited(string $email, ?AdminInvitation $except = null): void
    {
        $email = Str::lower(trim($email));

        if (User::query()->where('email', $email)->exists()) {
            throw AdminInvitationNotUsable::alreadyHasLogin();
        }

        $alreadyInvited = AdminInvitation::query()
            ->waiting()
            ->where('email', $email)
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists();

        if ($alreadyInvited) {
            throw AdminInvitationNotUsable::alreadyInvited();
        }
    }

    private function email(AdminInvitation $invitation, string $token): void
    {
        Mail::to($invitation->email)->queue(new AdminInvitationMail(
            $invitation,
            acceptUrl: route('invitations.show', $token),
            declineUrl: route('invitations.decline-form', $token),
        ));
    }
}
