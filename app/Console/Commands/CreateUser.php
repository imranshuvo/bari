<?php

namespace App\Console\Commands;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class CreateUser extends Command
{
    protected $signature = 'user:create
        {--name= : Their name}
        {--email= : Their email address}
        {--password= : Leave out to type it, or use "random"}
        {--project= : Project name or id to add them to}
        {--role=editor : owner, editor or viewer}
        {--locale=en : en or bn}';

    protected $description = 'Create a user, and optionally add them to a project';

    public function handle(): int
    {
        $name = $this->option('name') ?: text('Name', required: true);
        $email = Str::lower(trim($this->option('email') ?: text('Email', required: true)));

        $validator = Validator::make(['email' => $email], ['email' => ['email', 'unique:users,email']]);

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first('email'));

            return self::FAILURE;
        }

        $plain = $this->option('password') ?: password('Password (leave empty for a random one)');
        $generated = blank($plain) || $plain === 'random';
        $plain = $generated ? Str::password(14, symbols: false) : $plain;

        $project = $this->project();

        if ($project === false) {
            return self::FAILURE;
        }

        $role = ProjectRole::tryFrom((string) $this->option('role'));

        if (! $role) {
            $this->components->error('Role must be owner, editor or viewer.');

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($plain),
            'locale' => array_key_exists((string) $this->option('locale'), User::LOCALES) ? $this->option('locale') : 'en',
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        if ($project && ! $project->roleOf($user)) {
            $project->addMember($user, $role);
        }

        $this->components->info("Created {$user->email}".($project ? " ({$role->value} in {$project->name})" : ''));

        if ($generated) {
            $this->components->twoColumnDetail('Password', $plain);
        }

        $this->components->twoColumnDetail('Sign in at', url('/login'));

        return self::SUCCESS;
    }

    /** null = no project, false = not found. */
    private function project(): Project|false|null
    {
        $option = $this->option('project');

        if ($option) {
            $project = Project::whereKey(is_numeric($option) ? (int) $option : 0)
                ->orWhereRaw('lower(name) = ?', [Str::lower($option)])
                ->first();

            if (! $project) {
                $this->components->error("No project called \"{$option}\".");

                return false;
            }

            return $project;
        }

        if (! $this->input->isInteractive() || Project::doesntExist()) {
            return null;
        }

        $choice = select(
            'Add them to a project?',
            ['none' => 'No, they create their own'] + Project::orderBy('name')->pluck('name', 'id')->all(),
        );

        if ($choice === 'none') {
            return null;
        }

        $role = select('Role', ['editor' => 'Editor – add and edit costs', 'viewer' => 'Viewer – can only look', 'owner' => 'Owner – everything']);
        $this->input->setOption('role', $role);

        return Project::find($choice);
    }
}
