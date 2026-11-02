<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Filament\Pages\Auth\Register;
use App\Models\Invitation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PhoneJoinTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private Invitation $invitation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutDefer();
        config(['services.waha.url' => 'http://waha.test', 'services.sms_gate.username' => null]);
        Http::fake(['waha.test/*' => Http::response(['id' => 'ok'])]);

        $this->project = Project::factory()->create(['name' => 'Our new house']);
        $this->invitation = $this->project->invitations()->create(['phone' => '01712345678', 'role' => ProjectRole::Editor, 'locale' => 'bn']);
    }

    public function test_the_join_page_asks_only_for_a_name_and_language(): void
    {
        $this->get($this->invitation->url())->assertRedirect('/register?invitation='.$this->invitation->token);

        $this->get('/register?invitation='.$this->invitation->token)
            ->assertOk()
            ->assertSee('Our new house')
            ->assertSee('+880 17•• •••678')
            ->assertDontSee('Confirm password');
    }

    private function sentCode(): string
    {
        $text = collect(Http::recorded())->map(fn (array $pair) => $pair[0]['text'] ?? '')->filter()->last();
        preg_match('/(\d{6})/', (string) $text, $match);

        return $match[1] ?? '';
    }

    public function test_joining_needs_the_code_sent_to_the_number_then_signs_in(): void
    {
        $page = Livewire::withQueryParams(['invitation' => $this->invitation->token])
            ->test(Register::class)
            ->assertSet('data.locale', 'bn')
            ->fillForm(['name' => 'Rina', 'locale' => 'bn'])
            ->call('register')
            ->assertHasNoFormErrors()
            ->assertSet('joinCodeSent', true);

        $this->assertSame(0, User::count());
        $this->assertGuest();
        Http::assertSent(fn (Request $r) => $r['chatId'] === '8801712345678@c.us');

        $page->fillForm(['name' => 'Rina', 'locale' => 'bn', 'code' => $this->sentCode()])
            ->call('register')
            ->assertHasNoFormErrors()
            ->assertRedirect(filament()->getPanel('app')->getUrl($this->project));

        $rina = User::sole();
        $this->assertSame('Rina', $rina->name);
        $this->assertSame('+8801712345678', $rina->phone);
        $this->assertNotNull($rina->phone_verified_at);
        $this->assertNull($rina->email);
        $this->assertNull($rina->password);
        $this->assertSame('bn', $rina->locale);
        $this->assertSame(ProjectRole::Editor, $this->project->roleOf($rina));
        $this->assertNotNull($this->invitation->fresh()->accepted_at);
        $this->assertAuthenticatedAs($rina);
    }

    public function test_someone_holding_the_link_without_the_phone_cannot_join(): void
    {
        Livewire::withQueryParams(['invitation' => $this->invitation->token])
            ->test(Register::class)
            ->fillForm(['name' => 'Not Rina', 'locale' => 'en'])
            ->call('register')
            ->fillForm(['name' => 'Not Rina', 'locale' => 'en', 'code' => '000000'])
            ->call('register')
            ->assertHasFormErrors(['code']);

        $this->assertSame(0, User::count());
        $this->assertGuest();
    }

    public function test_joining_says_so_when_no_code_can_be_sent(): void
    {
        config(['services.waha.url' => null]);

        Livewire::withQueryParams(['invitation' => $this->invitation->token])
            ->test(Register::class)
            ->fillForm(['name' => 'Rina', 'locale' => 'bn'])
            ->call('register')
            ->assertHasFormErrors(['name'])
            ->assertSet('joinCodeSent', false);
    }

    public function test_a_name_is_required(): void
    {
        Livewire::withQueryParams(['invitation' => $this->invitation->token])
            ->test(Register::class)
            ->fillForm(['name' => ''])
            ->call('register')
            ->assertHasFormErrors(['name' => 'required']);

        $this->assertSame(0, User::count());
    }

    public function test_someone_whose_number_has_an_account_is_asked_to_sign_in(): void
    {
        $this->invitation->delete();
        User::factory()->phoneOnly('+8801712345678')->create();
        $invitation = $this->project->invitations()->create(['phone' => '+8801712345678', 'role' => ProjectRole::Viewer]);

        $this->get($invitation->url())->assertRedirect('/login');
    }

    public function test_a_used_phone_invitation_cannot_be_used_again(): void
    {
        $this->invitation->update(['accepted_at' => now()]);

        $this->get('/register?invitation='.$this->invitation->token)->assertRedirect('/login');
    }
}
