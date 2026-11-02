<?php

namespace App\Console\Commands;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use App\Support\PhoneNumber;
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
        {--phone= : Their phone number (they sign in with a code on WhatsApp)}
        {--password= : Leave out to type it, or use "random"}
        {--project= : Project name or id to add them to}
        {--role=editor : owner, editor or viewer}
        {--locale=en : en or bn}';

    protected $description = 'Create a user, and optionally add them to a project';

    public function handle(): int
    {
        $name = $this->option('name') ?: text('Name', required: true);
        [$email, $phone] = $this->contact();

        if ($email === null && $phone === null) {
            $this->components->error('Give an email (--email) or a phone number (--phone).');

            return self::FAILURE;
        }

        $validator = Validator::make(['email' => $email, 'phone' => $phone], [
            'email' => ['nullable', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'unique:users,phone'],
        ]);

        if ($phone !== null && ! PhoneNumber::isValid($this->option('phone') ?: $phone)) {
            $this->components->error('That phone number is not valid. Use the country code, e.g. +8801712345678, or 01712345678.');

            return self::FAILURE;
        }

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

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
            'phone' => $phone,
            'password' => Hash::make($plain),
            'locale' => array_key_exists((string) $this->option('locale'), User::LOCALES) ? $this->option('locale') : 'en',
        ]);

        $user->forceFill([
            'email_verified_at' => $email ? now() : null,
            'phone_verified_at' => $phone ? now() : null,
        ])->save();

        if ($project && ! $project->roleOf($user)) {
            $project->addMember($user, $role);
        }

        $this->components->info('Created '.($user->email ?? $user->phone).($project ? " ({$role->value} in {$project->name})" : ''));

        if ($generated) {
            $this->components->twoColumnDetail('Password', $plain);
        }

        $this->components->twoColumnDetail('Sign in at', url('/login'));

        return self::SUCCESS;
    }

    /** @return array{0: ?string, 1: ?string} [email, phone] */
    private function contact(): array
    {
        $email = $this->option('email');
        $phone = $this->option('phone');

        if (! $email && ! $phone && $this->input->isInteractive()) {
            $email = text('Email (leave empty to use a phone number instead)');
            $phone = $email ? null : text('Phone number', placeholder: '01712345678', required: true);
        }

        return [
            filled($email) ? Str::lower(trim($email)) : null,
            filled($phone) ? PhoneNumber::normalize($phone) : null,
        ];
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
