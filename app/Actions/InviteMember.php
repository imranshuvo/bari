<?php

namespace App\Actions;

use App\Enums\ProjectRole;
use App\Models\Invitation;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectInvitation;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class InviteMember
{
    /**
     * Adds an existing user straight away; otherwise creates an invitation link and emails it.
     *
     * @return array{status: 'added'|'already'|'invited', invitation?: Invitation, user?: User}
     */
    public static function handle(Project $project, string $email, ProjectRole $role, User $invitedBy): array
    {
        $email = Str::lower(trim($email));
        $user = User::whereRaw('lower(email) = ?', [$email])->first();

        if ($user) {
            if ($project->roleOf($user)) {
                return ['status' => 'already', 'user' => $user];
            }

            $project->addMember($user, $role);

            return ['status' => 'added', 'user' => $user];
        }

        $invitation = $project->invitations()->pending()->where('email', $email)->first()
            ?? $project->invitations()->create(['email' => $email, 'role' => $role, 'invited_by' => $invitedBy->id]);

        $invitation->update(['role' => $role]);

        Notification::route('mail', $email)->notify(new ProjectInvitation($invitation));

        return ['status' => 'invited', 'invitation' => $invitation];
    }
}
