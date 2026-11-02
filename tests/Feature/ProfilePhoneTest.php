<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Filament\Pages\Auth\EditProfile;
use App\Models\Project;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ProfilePhoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutDefer();
        config(['services.waha.url' => 'http://waha.test', 'services.sms_gate.username' => null]);
        Http::fake(['waha.test/*' => Http::response(['id' => 'ok'])]);
        Filament::setCurrentPanel('app');
    }

    private function signIn(User $user): User
    {
        Project::factory()->create()->addMember($user, ProjectRole::Owner);
        $this->actingAs($user);

        return $user;
    }

    private function sentCode(): string
    {
        $text = collect(Http::recorded())->map(fn (array $pair) => $pair[0]['text'] ?? '')->filter()->last();
        preg_match('/(\d{6})/', (string) $text, $match);

        return $match[1] ?? '';
    }

    public function test_adding_a_phone_number_needs_the_code_sent_to_it(): void
    {
        $user = $this->signIn(User::factory()->create(['email' => 'imran@example.com']));

        $page = Livewire::test(EditProfile::class)
            ->fillForm(['phone' => '01712345678'])
            ->call('sendPhoneCode')
            ->assertHasNoFormErrors()
            ->assertSet('phoneCodeSentTo', '+8801712345678');

        Http::assertSent(fn (Request $r) => $r['chatId'] === '8801712345678@c.us');

        $page->fillForm(['phone' => '01712345678', 'phone_code' => $this->sentCode()])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertSame('+8801712345678', $user->phone);
        $this->assertNotNull($user->phone_verified_at);
    }

    public function test_a_wrong_code_does_not_save_the_number(): void
    {
        $user = $this->signIn(User::factory()->create());

        Livewire::test(EditProfile::class)
            ->fillForm(['phone' => '01712345678'])
            ->call('sendPhoneCode')
            ->fillForm(['phone' => '01712345678', 'phone_code' => '000000'])
            ->call('save')
            ->assertHasFormErrors(['phone_code']);

        $this->assertNull($user->fresh()->phone);
    }

    public function test_a_number_on_another_account_is_refused(): void
    {
        User::factory()->phoneOnly('+8801712345678')->create();
        $this->signIn(User::factory()->create());

        Livewire::test(EditProfile::class)
            ->fillForm(['phone' => '+880 1712 345678'])
            ->call('sendPhoneCode')
            ->assertHasFormErrors(['phone'])
            ->assertSet('phoneCodeSentTo', null);

        Http::assertNothingSent();
    }

    public function test_phone_only_users_see_no_password_fields(): void
    {
        $this->signIn(User::factory()->phoneOnly()->create());

        Livewire::test(EditProfile::class)
            ->assertFormFieldHidden('password')
            ->assertFormFieldHidden('currentPassword')
            ->assertFormFieldVisible('phone');
    }

    public function test_email_can_only_be_cleared_with_a_confirmed_phone(): void
    {
        $emailOnly = User::factory()->create(['email' => 'only@example.com']);
        $this->signIn($emailOnly);

        Livewire::test(EditProfile::class)
            ->fillForm(['email' => ''])
            ->call('save')
            ->assertHasFormErrors(['email']);

        $both = User::factory()->create(['email' => 'both@example.com', 'phone' => '+8801712345678', 'phone_verified_at' => now()]);
        $this->signIn($both);

        Livewire::test(EditProfile::class)
            ->fillForm(['email' => '', 'currentPassword' => 'password'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($both->fresh()->email);
    }

    public function test_the_profile_page_opens_for_someone_in_a_project(): void
    {
        $user = User::factory()->create();
        Project::factory()->create()->addMember($user, ProjectRole::Owner);

        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('Phone number');
    }

    public function test_someone_without_a_project_is_sent_to_create_one_first(): void
    {
        $this->actingAs(User::factory()->create())->get('/profile')->assertRedirect(Filament::getPanel('app')->getTenantRegistrationUrl());
    }
}
