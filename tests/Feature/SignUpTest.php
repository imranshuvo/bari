<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Filament\Pages\Auth\Register;
use App\Models\Project;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class SignUpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
    }

    public function test_the_login_page_has_no_sign_up_link_when_sign_up_is_closed(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('/register');
    }

    public function test_the_sign_up_link_comes_back_when_sign_up_is_open(): void
    {
        config(['planner.open_registration' => true]);

        $this->get('/login')->assertOk()->assertSee('/register');
    }

    public function test_strangers_cannot_open_the_sign_up_page(): void
    {
        $this->get('/register')->assertRedirect('/login');
        $this->get('/register?invitation=made-up')->assertRedirect('/login');
    }

    public function test_an_invited_person_signs_up_with_the_invited_email_and_joins(): void
    {
        $project = Project::factory()->create(['name' => 'Our new house']);
        $invitation = $project->invitations()->create(['email' => 'partner@example.com', 'role' => ProjectRole::Editor]);

        // The link in the email/WhatsApp sends a newcomer to sign up.
        $this->get($invitation->url())->assertRedirect('/register?invitation='.$invitation->token);

        $this->get('/register?invitation='.$invitation->token)
            ->assertOk()
            ->assertSee('You have been invited to Our new house');

        Livewire::withQueryParams(['invitation' => $invitation->token])
            ->test(Register::class)
            ->assertSet('data.email', 'partner@example.com')
            ->fillForm([
                'name' => 'Partner',
                'email' => 'hacker@example.com', // changed in the browser: ignored
                'password' => 'a-good-password',
                'passwordConfirmation' => 'a-good-password',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $user = User::sole();
        $this->assertSame('partner@example.com', $user->email);
        $this->assertSame(ProjectRole::Editor, $project->roleOf($user));
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_a_used_invitation_cannot_create_another_account(): void
    {
        $project = Project::factory()->create();
        $invitation = $project->invitations()->create(['email' => 'once@example.com', 'role' => ProjectRole::Viewer]);
        $invitation->update(['accepted_at' => now()]);

        $this->get('/register?invitation='.$invitation->token)->assertRedirect('/login');
    }

    public function test_an_invited_person_who_already_has_an_account_is_asked_to_sign_in(): void
    {
        $project = Project::factory()->create();
        User::factory()->create(['email' => 'known@example.com']);
        $invitation = $project->invitations()->create(['email' => 'known@example.com', 'role' => ProjectRole::Viewer]);

        // (Creating the invitation after the user exists keeps it pending.)
        $this->get($invitation->url())->assertRedirect('/login');
    }

    public function test_the_command_creates_a_user_and_adds_them_to_a_project(): void
    {
        $project = Project::factory()->create(['name' => 'Our new house']);

        $this->artisan('user:create', [
            '--name' => 'Karim',
            '--email' => 'Karim@Example.com',
            '--password' => 'random',
            '--project' => 'our new house',
            '--role' => 'viewer',
            '--locale' => 'bn',
        ])->assertSuccessful();

        $user = User::sole();
        $this->assertSame('karim@example.com', $user->email);
        $this->assertSame('bn', $user->locale);
        $this->assertSame(ProjectRole::Viewer, $project->roleOf($user));
    }

    public function test_the_command_refuses_a_taken_email_or_unknown_project(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->artisan('user:create', ['--name' => 'X', '--email' => 'taken@example.com', '--password' => 'random'])
            ->assertFailed();

        $this->artisan('user:create', ['--name' => 'Y', '--email' => 'new@example.com', '--password' => 'random', '--project' => 'Nope'])
            ->assertFailed();

        $this->assertSame(1, User::count());
    }

    public function test_the_password_command_sets_a_new_password(): void
    {
        $user = User::factory()->create(['email' => 'karim@example.com']);

        $this->artisan('user:password', ['email' => 'Karim@Example.com', '--password' => 'new-secret-123'])
            ->assertSuccessful();

        $this->assertTrue(Hash::check('new-secret-123', $user->fresh()->password));

        $this->artisan('user:password', ['email' => 'nobody@example.com', '--password' => 'x'])
            ->assertFailed();
    }

    public function test_local_and_international_formats_match_the_same_user(): void
    {
        $user = User::factory()->create(['email' => 'Rina@Example.com', 'phone' => '01712-345678']);

        $this->assertSame('+8801712345678', $user->fresh()->phone);
        $this->assertSame('rina@example.com', $user->fresh()->email);

        foreach (['01712-345678', '+880 1712 345678', '০১৭১২৩৪৫৬৭৮', '008801712345678', 'RINA@example.com '] as $login) {
            $this->assertTrue($user->is(User::findByLogin($login)), "Not found by {$login}");
        }

        $this->assertNull(User::findByLogin('01800000000'));
        $this->assertNull(User::findByLogin(''));
    }

    public function test_the_command_creates_a_phone_only_user(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['name' => 'Our new house']);
        $project->addMember($owner, ProjectRole::Owner);

        $this->artisan('user:create', ['--name' => 'Rina', '--phone' => '01712345678', '--project' => 'Our new house', '--role' => 'editor', '--password' => 'random'])
            ->assertSuccessful();

        $rina = User::where('phone', '+8801712345678')->firstOrFail();
        $this->assertNull($rina->email);
        $this->assertNotNull($rina->phone_verified_at);
        $this->assertSame(ProjectRole::Editor, $project->roleOf($rina));

        $this->artisan('user:create', ['--name' => 'Nobody', '--password' => 'random', '--no-interaction' => true])
            ->assertFailed();
    }
}
