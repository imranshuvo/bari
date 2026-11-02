<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use App\Support\LoginCode;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class PhoneLoginTest extends TestCase
{
    use RefreshDatabase;

    private User $rina;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutDefer();
        config(['services.waha.url' => 'http://waha.test', 'services.sms_gate.username' => null]);
        Http::fake(['waha.test/*' => Http::response(['id' => 'ok'])]);
        Filament::setCurrentPanel('app');

        $this->rina = User::factory()->phoneOnly('+8801712345678')->create(['name' => 'Rina']);
    }

    private function askForCode(string $number = '01712345678'): Testable
    {
        return Livewire::test(Login::class)
            ->fillForm(['login' => $number])
            ->call('authenticate')
            ->assertHasNoFormErrors();
    }

    private function sentCode(): string
    {
        $texts = collect(Http::recorded())->map(fn (array $pair) => $pair[0]['text'] ?? '')->filter();
        preg_match('/(\d{6})/', (string) $texts->last(), $match);

        return $match[1] ?? '';
    }

    public function test_a_code_on_whatsapp_signs_you_in_and_remembers_you(): void
    {
        $page = $this->askForCode()->assertSet('codeSentTo', '+8801712345678')->assertSee('+880 17•• •••678');

        Http::assertSent(fn (Request $r) => $r['chatId'] === '8801712345678@c.us'
            && preg_match('/^Your Bari code is \d{6}\. It expires in 10 minutes\.$/', $r['text']));

        $page->fillForm(['code' => $this->sentCode()])->call('authenticate')->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($this->rina);
        $this->assertNotNull($this->rina->fresh()->remember_token);
    }

    public function test_the_code_comes_in_the_users_language(): void
    {
        $this->rina->update(['locale' => 'bn']);

        $this->askForCode();

        Http::assertSent(fn (Request $r) => ! str_contains($r['text'], 'Your Bari code'));
    }

    public function test_a_wrong_code_is_refused_and_five_tries_kill_it(): void
    {
        $page = $this->askForCode();
        $code = $this->sentCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        $page->fillForm(['code' => $wrong])->call('authenticate')->assertHasFormErrors(['code']);
        $this->assertGuest();

        foreach (range(2, 5) as $try) {
            $page->fillForm(['code' => $wrong])->call('authenticate');
        }

        $page->fillForm(['code' => $code])->call('authenticate')->assertHasFormErrors(['code']);
        $this->assertGuest();
    }

    public function test_a_code_expires_after_ten_minutes(): void
    {
        $page = $this->askForCode();
        $code = $this->sentCode();

        $this->travel(11)->minutes();

        $page->fillForm(['code' => $code])->call('authenticate')->assertHasFormErrors(['code']);
        $this->assertGuest();
    }

    public function test_only_one_code_a_minute(): void
    {
        $page = $this->askForCode();
        $page->call('resendCode');

        Http::assertSentCount(1);

        $this->travel(61)->seconds();
        $page->call('resendCode');

        Http::assertSentCount(2);
    }

    public function test_an_unknown_number_gets_the_same_screen_and_no_message(): void
    {
        $this->askForCode('01800000000')->assertSet('codeSentTo', '+8801800000000');

        Http::assertNothingSent();
    }

    public function test_a_failed_send_still_shows_the_code_screen(): void
    {
        config(['services.waha.url' => 'http://down.test']);
        Http::fake(['down.test/*' => Http::response('down', 500)]);

        $this->askForCode()->assertSet('codeSentTo', '+8801712345678');
        $this->assertGuest();
    }

    public function test_phone_sign_in_says_so_when_nothing_is_set_up(): void
    {
        config(['services.waha.url' => null]);

        Livewire::test(Login::class)
            ->fillForm(['login' => '01712345678'])
            ->call('authenticate')
            ->assertHasFormErrors(['login'])
            ->assertSee("Sign-in by phone isn't available right now.")
            ->assertSet('codeSentTo', null);
    }

    public function test_email_and_password_still_work(): void
    {
        $owner = User::factory()->create(['email' => 'imran@example.com']);

        Livewire::test(Login::class)
            ->fillForm(['login' => 'Imran@Example.com', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($owner);
    }

    public function test_a_wrong_password_is_refused(): void
    {
        User::factory()->create(['email' => 'imran@example.com']);

        Livewire::test(Login::class)
            ->fillForm(['login' => 'imran@example.com', 'password' => 'nope'])
            ->call('authenticate')
            ->assertHasFormErrors(['login']);

        $this->assertGuest();
    }

    public function test_a_code_typed_in_bangla_digits_works(): void
    {
        $page = $this->askForCode();
        $bangla = strtr($this->sentCode(), ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);

        $page->fillForm(['code' => $bangla])->call('authenticate')->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($this->rina);
    }

    public function test_the_password_box_is_never_required_by_the_browser(): void
    {
        Livewire::test(Login::class)
            ->assertFormFieldExists('password', fn (TextInput $field) => ! $field->isRequired());
    }

    public function test_an_email_without_a_password_is_refused(): void
    {
        User::factory()->create(['email' => 'imran@example.com']);

        Livewire::test(Login::class)
            ->fillForm(['login' => 'imran@example.com'])
            ->call('authenticate')
            ->assertHasFormErrors(['password']);

        $this->assertGuest();
    }

    public function test_no_more_than_ten_codes_a_day_per_number(): void
    {
        foreach (range(1, 12) as $i) {
            LoginCode::send('+8801712345678');
            $this->travel(13)->minutes();
        }

        Http::assertSentCount(10);
    }
}
