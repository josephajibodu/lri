<?php

namespace App\Actions\AdminInvitations;

use App\Exceptions\AdminInvitationNotUsable;
use App\Models\AdminInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use SensitiveParameter;

/**
 * The invited person says yes or no. An invitation can be answered once, so each answer locks it first: pressing the
 * button twice, or answering in two tabs, can't make two logins.
 */
class AnswerAdminInvitation
{
    /**
     * Make the person's login, with the invitation's email address and roles.
     *
     * @throws AdminInvitationNotUsable
     */
    public function accept(AdminInvitation $invitation, string $name, #[SensitiveParameter] string $password): User
    {
        return DB::transaction(function () use ($invitation, $name, $password): User {
            $invitation = $this->lockIfWaiting($invitation);

            if (User::query()->where('email', $invitation->email)->exists()) {
                throw AdminInvitationNotUsable::canSignInInstead();
            }

            $user = User::query()->create([
                'name' => $name,
                'email' => $invitation->email,
                'password' => $password,
            ]);

            // Following the link from their inbox proves the address is theirs.
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->syncRoles($invitation->roles);

            $invitation->forceFill(['accepted_at' => now()])->save();

            return $user;
        });
    }

    /**
     * @throws AdminInvitationNotUsable
     */
    public function decline(AdminInvitation $invitation): void
    {
        DB::transaction(function () use ($invitation): void {
            $this->lockIfWaiting($invitation)->forceFill(['declined_at' => now()])->save();
        });
    }

    /**
     * @throws AdminInvitationNotUsable
     */
    private function lockIfWaiting(AdminInvitation $invitation): AdminInvitation
    {
        $locked = AdminInvitation::query()->lockForUpdate()->findOrFail($invitation->getKey());

        if (! $locked->isWaiting()) {
            throw AdminInvitationNotUsable::noLongerOpen();
        }

        return $locked;
    }
}
