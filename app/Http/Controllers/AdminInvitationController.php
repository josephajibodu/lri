<?php

namespace App\Http\Controllers;

use App\Actions\AdminInvitations\AnswerAdminInvitation;
use App\Enums\AdminInvitationStatus;
use App\Exceptions\AdminInvitationNotUsable;
use App\Http\Requests\AcceptAdminInvitationRequest;
use App\Models\AdminInvitation;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The invited person's side of an invitation to the admin, reached from the links in their email. Opening a link
 * never changes anything, because mail apps and scanners follow links on their own: only pressing a button accepts
 * or declines.
 */
class AdminInvitationController extends Controller
{
    /**
     * The form for accepting, or a page explaining why the invitation can't be answered any more.
     */
    public function show(string $token): View
    {
        $invitation = $this->findInvitation($token);

        if (! $invitation->isWaiting()) {
            return view('admin-invitations.closed', $this->closedPage($invitation));
        }

        return view('admin-invitations.accept', $this->pageData($invitation, $token));
    }

    /**
     * Make the person's login, sign them in and take them into the admin.
     */
    public function accept(AcceptAdminInvitationRequest $request, string $token, AnswerAdminInvitation $answer): RedirectResponse
    {
        try {
            $user = $answer->accept($this->findInvitation($token), $request->validated('name'), $request->validated('password'));
        } catch (AdminInvitationNotUsable $exception) {
            return redirect()->route('invitations.show', $token)->withErrors(['invitation' => $exception->getMessage()]);
        }

        // Start clean: a browser still signed in as someone else would otherwise be signed out on the next page.
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::login($user);

        Notification::make()
            ->title('Welcome, '.Str::before($user->name, ' ').'. You’re in.')
            ->success()
            ->send();

        return redirect()->route('filament.admin.home');
    }

    /**
     * Where the email's "decline" link lands: it only asks, so that a scanner opening the link can't decline.
     */
    public function confirmDecline(string $token): View|RedirectResponse
    {
        $invitation = $this->findInvitation($token);

        if (! $invitation->isWaiting()) {
            return redirect()->route('invitations.show', $token);
        }

        return view('admin-invitations.decline', $this->pageData($invitation, $token));
    }

    /**
     * Close the invitation without making a login.
     */
    public function decline(string $token, AnswerAdminInvitation $answer): RedirectResponse
    {
        try {
            $answer->decline($this->findInvitation($token));
        } catch (AdminInvitationNotUsable) {
            // It closed some other way first. The page it lands on explains how.
        }

        return redirect()->route('invitations.show', $token);
    }

    /**
     * @return array{invitation: AdminInvitation, acceptUrl: string, declineUrl: string, isPreview: bool}
     */
    private function pageData(AdminInvitation $invitation, string $token): array
    {
        return [
            'invitation' => $invitation,
            'acceptUrl' => route('invitations.show', $token),
            'declineUrl' => route('invitations.decline-form', $token),
            'isPreview' => false,
        ];
    }

    /**
     * @return array{heading: string, message: string, status: AdminInvitationStatus}
     */
    private function closedPage(AdminInvitation $invitation): array
    {
        $inviter = $invitation->inviterName();
        $status = $invitation->status();

        return ['status' => $status, ...match ($status) {
            AdminInvitationStatus::Accepted => [
                'heading' => 'You’ve already accepted.',
                'message' => 'This link has already been used. Sign in with the password you chose.',
            ],
            AdminInvitationStatus::Declined => [
                'heading' => 'You declined this invitation.',
                'message' => "This link no longer works. If you change your mind, ask {$inviter} to send a new invitation.",
            ],
            AdminInvitationStatus::Cancelled => [
                'heading' => 'This invitation was cancelled.',
                'message' => "This link no longer works. Ask {$inviter} if you think that’s a mistake.",
            ],
            default => [
                'heading' => 'This invitation has expired.',
                'message' => "Invitations only work for a short time, to keep the admin safe. Ask {$inviter} to send you a new one.",
            ],
        }];
    }

    private function findInvitation(string $token): AdminInvitation
    {
        return AdminInvitation::findByToken($token) ?? abort(404);
    }
}
