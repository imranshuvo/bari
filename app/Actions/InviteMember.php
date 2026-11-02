<?php

namespace App\Actions;

use App\Enums\NotificationChannel;
use App\Enums\ProjectRole;
use App\Models\Invitation;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectInvitation;
use App\Support\PhoneMessenger;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class InviteMember
{
    /**
     * Adds an existing user straight away; otherwise creates an invitation link and sends it
     * by email, or by WhatsApp (SMS as backup) for a phone number.
     *
     * @return array{status: 'added'|'already'|'invited', invitation?: Invitation, user?: User, sentVia?: ?NotificationChannel}
     */
    public static function handle(
        Project $project,
        string $contact,
        ProjectRole $role,
        User $invitedBy,
        string $locale = 'en',
        bool $sendCostUpdates = false,
    ): array {
        $isPhone = PhoneNumber::looksLikePhone($contact);
        $contact = $isPhone ? PhoneNumber::normalize($contact) : Str::lower(trim($contact));
        $field = $isPhone ? 'phone' : 'email';

        if ($isPhone && $sendCostUpdates) {
            $project->notificationRecipients()->firstOrCreate(
                ['channel' => NotificationChannel::WhatsApp, 'address' => $contact],
                ['locale' => $locale],
            );
        }

        if ($user = User::findByLogin($contact)) {
            if ($project->roleOf($user)) {
                return ['status' => 'already', 'user' => $user];
            }

            $project->addMember($user, $role);

            return ['status' => 'added', 'user' => $user];
        }

        $invitation = $project->invitations()->pending()->where($field, $contact)->first()
            ?? $project->invitations()->create([$field => $contact, 'role' => $role, 'locale' => $locale, 'invited_by' => $invitedBy->id]);

        $invitation->update(['role' => $role, 'locale' => $locale]);

        if (! $isPhone) {
            Notification::route('mail', $contact)->notify(new ProjectInvitation($invitation));

            return ['status' => 'invited', 'invitation' => $invitation, 'sentVia' => NotificationChannel::Email];
        }

        $text = __(':inviter added you to ":project" on Bari. Tap to join: :url', [
            'inviter' => $invitedBy->name,
            'project' => $project->name,
            'url' => $invitation->url(),
        ], $locale);

        return ['status' => 'invited', 'invitation' => $invitation, 'sentVia' => PhoneMessenger::send($contact, $text)];
    }
}
