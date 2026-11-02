<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public static function pages(): array
    {
        return [
            'overview' => ['', 'Paid so far'],
            'spent' => ['/spent', 'Spent'],
            'budget' => ['/budget', 'Total budget'],
            'agreed' => ['/agreed', 'Full architectural'],
            'suppliers' => ['/suppliers', 'Karim Construction'],
            'team' => ['/team', 'Bank Adviser'],
            'activity' => ['/activity', 'Activity'],
        ];
    }

    #[DataProvider('pages')]
    public function test_every_role_can_open_every_page(string $path, string $see): void
    {
        $project = Project::where('currency', 'BDT')->first();

        foreach (['owner@example.com', 'editor@example.com', 'viewer@example.com'] as $email) {
            $user = User::where('email', $email)->first();
            $user->update(['locale' => 'en']);

            $this->actingAs($user)
                ->get('/'.$project->id.$path)
                ->assertOk()
                ->assertSee($see);
        }
    }

    public function test_people_only_get_buttons_their_role_allows(): void
    {
        $project = Project::where('currency', 'BDT')->first();
        $html = fn (string $email, string $path) => $this->actingAs(User::where('email', $email)->first())
            ->get('/'.$project->id.$path)->getContent();

        $this->assertStringNotContainsString("mountAction('addCost')", $html('viewer@example.com', '/spent'));
        $this->assertStringNotContainsString("mountAction('create')", $html('viewer@example.com', '/spent'));
        $this->assertStringContainsString("mountAction('addCost')", $html('editor@example.com', '/spent'));
        $this->assertStringNotContainsString("mountAction('create')", $html('editor@example.com', '/budget'));
        $this->assertStringNotContainsString("mountAction('invite')", $html('editor@example.com', '/team'));
        $this->assertStringContainsString("mountAction('invite')", $html('owner@example.com', '/team'));
    }

    public function test_pages_render_in_bangla(): void
    {
        $project = Project::where('currency', 'BDT')->first();
        $viewer = User::where('email', 'viewer@example.com')->first();

        $this->actingAs($viewer)->get('/'.$project->id)->assertOk()->assertSee('৳');
    }

    public function test_project_settings_are_for_owners_only(): void
    {
        $project = Project::where('currency', 'BDT')->first();

        $this->actingAs(User::where('email', 'owner@example.com')->first())->get('/'.$project->id.'/profile')->assertOk();
        $this->actingAs(User::where('email', 'editor@example.com')->first())->get('/'.$project->id.'/profile')->assertNotFound();
    }

    public function test_people_cannot_open_projects_they_are_not_in(): void
    {
        $stranger = User::factory()->create();
        $project = Project::first();

        $this->actingAs($stranger)->get('/'.$project->id)->assertNotFound();
        $this->actingAs($stranger)->get('/'.$project->id.'/spent')->assertNotFound();
    }

    public function test_a_project_never_shows_another_projects_costs(): void
    {
        $owner = User::where('email', 'owner@example.com')->first();
        $owner->update(['locale' => 'en']);
        $danish = Project::where('currency', 'DKK')->first();

        $this->actingAs($owner)->get('/'.$danish->id.'/spent')
            ->assertOk()
            ->assertSee('Nordsjælland Tag')
            ->assertDontSee('Karim Construction')
            ->assertDontSee('Shah Cement');
    }

    public function test_new_users_are_sent_to_create_a_project(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertRedirect('/new');
        $this->get('/new')->assertOk()->assertSee('Create your house project');
    }

    public function test_guests_see_the_login_page_with_language_switch(): void
    {
        $this->get('/login')->assertOk()->assertSee('বাংলা');
    }
}
