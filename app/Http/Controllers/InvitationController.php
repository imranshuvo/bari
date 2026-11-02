<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvitationController
{
    /**
     * Signed in: join the project with the invited role.
     * Not signed in: sign in if the email has an account, otherwise create one.
     */
    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = Invitation::where('token', $token)->first();

        if (! $invitation || $invitation->accepted_at) {
            Notification::make()
                ->title(__('This invitation link is no longer valid.'))
                ->body(__('Ask the project owner to send you a new one.'))
                ->warning()
                ->send();

            return redirect('/');
        }

        $panel = Filament::getPanel('app');

        if (! $request->user()) {
            if (User::findByLogin($invitation->contact())) {
                return redirect()->guest($panel->getLoginUrl());
            }

            return redirect($panel->getRegistrationUrl(['invitation' => $invitation->token]));
        }

        $invitation->acceptFor($request->user());

        Notification::make()
            ->title(__('Welcome to :project', ['project' => $invitation->project->name]))
            ->success()
            ->send();

        return redirect($panel->getUrl($invitation->project));
    }
}
