<?php

namespace Tests\Feature;

use App\Actions\InviteMember;
use App\Enums\NotificationChannel;
use App\Enums\ProjectRole;
use App\Filament\Resources\Memberships\Pages\ManageMemberships;
use App\Models\Invitation;
use App\Models\NotificationRecipient;
use App\Models\Project;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PhoneInviteTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.waha.url' => 'http://waha.test', 'services.sms_gate.username' => null]);
        Http::fake(['waha.test/*' => Http::response(['id' => 'ok'])]);

        $this->project = Project::factory()->create(['name' => 'Our new house']);
        $this->owner = User::factory()->create(['name' => 'Imran']);
        $this->project->addMember($this->owner, ProjectRole::Owner);
        $this->actingAs($this->owner);
        Filament::setCurrentPanel('app');
        Filament::setTenant($this->project);
        Filament::bootCurrentPanel();
    }

    public function test_a_new_number_gets_an_invitation_on_whatsapp(): void
    {
        $result = InviteMember::handle($this->project, '01712-345678', ProjectRole::Editor, $this->owner, 'en');

        $invitation = Invitation::sole();
        $this->assertSame('invited', $result['status']);
        $this->assertSame(NotificationChannel::WhatsApp, $result['sentVia']);
        $this->assertSame('+8801712345678', $invitation->phone);
        $this->assertNull($invitation->email);
        $this->assertSame('en', $invitation->locale);

        Http::assertSent(fn (Request $r) => $r['chatId'] === '8801712345678@c.us'
            && $r['text'] === 'Imran added you to "Our new house" on Bari. Tap to join: '.$invitation->url());
    }

    public function test_the_invitation_text_is_in_the_chosen_language(): void
    {
        InviteMember::handle($this->project, '01712345678', ProjectRole::Viewer, $this->owner, 'bn');

        Http::assertSent(fn (Request $r) => str_contains($r['text'], 'Our new house') && ! str_contains($r['text'], 'Tap to join'));
        $this->assertSame('bn', Invitation::sole()->locale);
    }

    public function test_an_existing_users_number_is_added_straight_away(): void
    {
        $rina = User::factory()->phoneOnly('+8801712345678')->create();

        $result = InviteMember::handle($this->project, '01712 345678', ProjectRole::Viewer, $this->owner);

        $this->assertSame('added', $result['status']);
        $this->assertSame(ProjectRole::Viewer, $this->project->roleOf($rina));
        $this->assertSame(0, Invitation::count());
        Http::assertNothingSent();
    }

    public function test_inviting_the_same_number_twice_reuses_the_invitation(): void
    {
        InviteMember::handle($this->project, '01712345678', ProjectRole::Viewer, $this->owner);
        InviteMember::handle($this->project, '+880 1712 345678', ProjectRole::Editor, $this->owner);

        $this->assertSame(1, Invitation::count());
        $this->assertSame(ProjectRole::Editor, Invitation::sole()->role);
        Http::assertSentCount(2);
    }

    public function test_they_can_also_get_cost_updates(): void
    {
        InviteMember::handle($this->project, '01712345678', ProjectRole::Editor, $this->owner, 'bn', sendCostUpdates: true);
        InviteMember::handle($this->project, '01712345678', ProjectRole::Editor, $this->owner, 'bn', sendCostUpdates: true);

        $recipient = NotificationRecipient::sole();
        $this->assertSame(NotificationChannel::WhatsApp, $recipient->channel);
        $this->assertSame('+8801712345678', $recipient->address);
        $this->assertSame('bn', $recipient->locale);
        $this->assertSame($this->project->id, $recipient->project_id);
    }

    public function test_the_invitation_is_kept_when_nothing_can_send_it(): void
    {
        config(['services.waha.url' => 'http://down.test']);
        Http::fake(['down.test/*' => Http::response('down', 500)]);

        $result = InviteMember::handle($this->project, '01712345678', ProjectRole::Editor, $this->owner);

        $this->assertSame('invited', $result['status']);
        $this->assertNull($result['sentVia']);
        $this->assertSame(1, Invitation::count());
    }

    public function test_the_team_page_invites_by_number(): void
    {
        Livewire::test(ManageMemberships::class)
            ->callAction('invite', data: ['contact' => '01712345678', 'role' => 'editor', 'locale' => 'bn', 'send_cost_updates' => true])
            ->assertHasNoActionErrors();

        $this->assertSame('+8801712345678', Invitation::sole()->phone);
        $this->assertSame(1, NotificationRecipient::count());
    }

    public function test_the_team_page_rejects_something_that_is_neither(): void
    {
        Livewire::test(ManageMemberships::class)
            ->callAction('invite', data: ['contact' => 'hello', 'role' => 'editor'])
            ->assertHasActionErrors(['contact']);

        $this->assertSame(0, Invitation::count());
    }

    public function test_someone_created_with_an_invited_number_joins_the_project(): void
    {
        $this->project->invitations()->create(['phone' => '01712345678', 'role' => ProjectRole::Viewer]);

        $rina = User::factory()->phoneOnly('+8801712345678')->create();

        $this->assertSame(ProjectRole::Viewer, $this->project->roleOf($rina));
    }
}
