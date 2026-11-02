<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use function Laravel\Prompts\password;

class SetUserPassword extends Command
{
    protected $signature = 'user:password
        {email : Their email address}
        {--password= : Leave out to type it, or use "random"}';

    protected $description = 'Give someone a new password (they are signed out everywhere)';

    public function handle(): int
    {
        $user = User::where('email', Str::lower(trim($this->argument('email'))))->first();

        if (! $user) {
            $this->components->error("No account with the email {$this->argument('email')}.");

            return self::FAILURE;
        }

        $plain = $this->option('password') ?: password('New password (leave empty for a random one)');
        $generated = blank($plain) || $plain === 'random';
        $plain = $generated ? Str::password(14, symbols: false) : $plain;

        $user->forceFill([
            'password' => Hash::make($plain),
            'remember_token' => Str::random(60),
        ])->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        $this->components->info("New password set for {$user->email}");

        if ($generated) {
            $this->components->twoColumnDetail('Password', $plain);
        }

        return self::SUCCESS;
    }
}
