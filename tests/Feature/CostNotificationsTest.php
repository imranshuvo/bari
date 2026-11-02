<?php

namespace Tests\Feature;

use App\Enums\NotificationChannel;
use App\Enums\ProjectRole;
use App\Filament\Pages\Tenancy\EditProject;
use App\Livewire\AddCostButton;
use App\Models\Category;
use App\Models\Cost;
use App\Models\NotificationRecipient;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\CostChanged;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class CostNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private Category $roof;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.waha.url' => 'http://waha.test',
            'services.waha.key' => 'waha-secret',
            'services.sms_gate.username' => 'phone-user',
            'services.sms_gate.password' => 'phone-pass',
        ]);

        $this->project = Project::factory()->create(['name' => 'Our new house', 'currency' => 'BDT']);
        $this->roof = Category::factory()->for($this->project)->create(['name' => 'Roof']);
    }

    private function as(ProjectRole $role, string $name = 'Rahim'): User
    {
        $user = User::factory()->create(['name' => $name]);
        $this->project->addMember($user, $role);
        $this->actingAs($user);
        Filament::setCurrentPanel('app');
        Filament::setTenant($this->project);
        Filament::bootCurrentPanel();

        return $user;
    }

    private function recipient(NotificationChannel $channel, string $address, string $locale = 'en'): NotificationRecipient
    {
        return $this->project->notificationRecipients()->create(compact('channel', 'address', 'locale'));
    }

    /** @return array<int, CostChanged> */
    private function sentTo(string $driver, string $address): array
    {
        return collect(Notification::sentNotifications()[AnonymousNotifiable::class] ?? [])
            ->flatMap(fn (array $byClass) => $byClass[CostChanged::class] ?? [])
            ->filter(fn (array $sent) => ($sent['notifiable']->routes[$driver] ?? null) === $address)
            ->pluck('notification')
            ->values()
            ->all();
    }

    public function test_adding_a_cost_messages_everyone_on_the_list_in_their_language(): void
    {
        Notification::fake();
        $this->recipient(NotificationChannel::Email, 'Mum@Example.com');
        $this->recipient(NotificationChannel::WhatsApp, '+880 1712-345678', 'bn');
        $this->recipient(NotificationChannel::Sms, '00880১৭১২৩৪৫৬৭৯');
        $this->as(ProjectRole::Editor);

        Livewire::test(AddCostButton::class)->callAction('addCost', data: [
            'amount' => '25000',
            'supplier_name' => 'Shah Cement',
            'description' => '40 bags of cement',
            'category_id' => $this->roof->id,
            'date' => now()->toDateString(),
            'is_paid' => 1,
        ]);

        [$email] = $this->sentTo('mail', 'mum@example.com');
        [$whatsapp] = $this->sentTo(WhatsAppChannel::class, '+8801712345678');
        [$sms] = $this->sentTo(SmsChannel::class, '+8801712345679');

        $this->assertSame('en', $email->locale);
        $this->assertSame('bn', $whatsapp->locale);

        $this->app->setLocale('bn');
        $text = $whatsapp->toWhatsApp(new AnonymousNotifiable);
        $this->assertStringContainsString('Rahim একটি খরচ যোগ করেছেন', $text);
        $this->assertStringContainsString('৳২৫,০০০ · Shah Cement · 40 bags of cement · Roof', $text);
        $this->assertStringContainsString('পরিশোধিত', $text);

        $this->app->setLocale('en');
        $this->assertSame(
            'Our new house: Rahim added a cost. ৳25,000 · Shah Cement · 40 bags of cement · Roof · Paid',
            $sms->toSms(new AnonymousNotifiable),
        );
        $this->assertSame('Our new house: Rahim added a cost', $email->toMail(new AnonymousNotifiable)->subject);
    }

    public function test_changing_the_amount_says_what_changed(): void
    {
        $this->as(ProjectRole::Editor);
        $cost = Cost::factory()->in($this->roof)->create(['amount' => 20_000_00, 'is_paid' => false, 'due_date' => '2026-12-01']);
        Notification::fake();
        $this->recipient(NotificationChannel::WhatsApp, '+8801712345678');

        $cost->update(['amount' => 25_000_00]);

        [$message] = $this->sentTo(WhatsAppChannel::class, '+8801712345678');
        $text = $message->toWhatsApp(new AnonymousNotifiable);
        $this->assertStringContainsString('Rahim changed a cost', $text);
        $this->assertStringContainsString('Amount: ৳20,000 → ৳25,000', $text);
        $this->assertStringContainsString('Pay by 1 Dec 2026', $text);
    }

    public function test_marking_paid_and_deleting_send_their_own_message(): void
    {
        $this->as(ProjectRole::Owner, 'Imran');
        $supplier = Supplier::factory()->for($this->project)->create(['name' => 'Rod shop']);
        $cost = Cost::factory()->in($this->roof)->create(['supplier_id' => $supplier->id, 'is_paid' => false, 'due_date' => now()->addWeek()]);
        Notification::fake();
        $this->recipient(NotificationChannel::Sms, '+4520202020');

        $cost->update(['is_paid' => true]);
        $cost->delete();

        $texts = collect($this->sentTo(SmsChannel::class, '+4520202020'))->map(fn ($n) => $n->toSms(new AnonymousNotifiable));
        $this->assertCount(2, $texts);
        $this->assertStringContainsString('Imran marked a cost as paid', $texts[0]);
        $this->assertStringContainsString('Imran deleted a cost', $texts[1]);
        $this->assertStringContainsString('Rod shop', $texts[1]);
    }

    public function test_small_edits_and_projects_without_a_list_stay_quiet(): void
    {
        $this->as(ProjectRole::Editor);
        $cost = Cost::factory()->in($this->roof)->create();
        Notification::fake();

        $cost->update(['amount' => 1_00]); // nobody on the list yet
        $this->recipient(NotificationChannel::Email, 'mum@example.com');
        $cost->update(['attachments' => ['bills/receipt.jpg']]); // adding a photo is not news

        Notification::assertNothingSent();
    }

    public function test_channels_the_server_has_not_set_up_are_skipped(): void
    {
        config(['services.waha.url' => null]);
        $this->as(ProjectRole::Editor);
        Notification::fake();
        $this->recipient(NotificationChannel::WhatsApp, '+8801712345678');
        $this->recipient(NotificationChannel::Email, 'mum@example.com');

        Cost::factory()->in($this->roof)->create();

        $this->assertCount(0, $this->sentTo(WhatsAppChannel::class, '+8801712345678'));
        $this->assertCount(1, $this->sentTo('mail', 'mum@example.com'));
    }

    public function test_whatsapp_and_sms_are_delivered_through_waha_and_the_phone(): void
    {
        Http::fake(['*' => Http::response(['id' => 'ok'])]);
        $this->as(ProjectRole::Editor);
        $this->recipient(NotificationChannel::WhatsApp, '+8801712345678');
        $this->recipient(NotificationChannel::Sms, '+8801712345679');

        Cost::factory()->in($this->roof)->create(['amount' => 5_000_00]);

        Http::assertSent(fn (Request $r) => $r->url() === 'http://waha.test/api/sendText'
            && $r->hasHeader('X-Api-Key', 'waha-secret')
            && $r['chatId'] === '8801712345678@c.us'
            && $r['session'] === 'default'
            && str_contains($r['text'], '৳5,000'));

        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.sms-gate.app/3rdparty/v1/messages'
            && $r->hasHeader('Authorization', 'Basic '.base64_encode('phone-user:phone-pass'))
            && $r['phoneNumbers'] === ['+8801712345679']
            && str_contains($r['textMessage']['text'], 'Rahim added a cost'));
    }

    public function test_a_failing_message_never_stops_someone_saving_a_cost(): void
    {
        Http::fake(['*' => Http::response('down', 500)]);
        $this->as(ProjectRole::Editor);
        $this->recipient(NotificationChannel::WhatsApp, '+8801712345678');

        Cost::factory()->in($this->roof)->create();

        $this->assertSame(1, Cost::count());
    }

    public function test_the_owner_manages_the_list_and_sends_a_test_message(): void
    {
        Http::fake(['*' => Http::response(['id' => 'ok'])]);
        $this->as(ProjectRole::Owner);

        Livewire::test(EditProject::class)
            ->set('data.notificationRecipients', [
                'a' => ['channel' => 'whatsapp', 'address' => '1712345678', 'name' => 'Mum', 'locale' => 'bn'],
            ])
            ->call('save')
            ->assertHasErrors(['data.notificationRecipients.a.address']);

        Livewire::test(EditProject::class)
            ->set('data.notificationRecipients', [
                'a' => ['channel' => 'whatsapp', 'address' => '+880 1712 345678', 'name' => 'Mum', 'locale' => 'bn'],
                'b' => ['channel' => 'email', 'address' => 'Bank@Example.com', 'name' => null, 'locale' => 'en'],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEqualsCanonicalizing(
            ['+8801712345678', 'bank@example.com'],
            $this->project->notificationRecipients()->pluck('address')->all(),
        );

        Livewire::test(EditProject::class)
            ->assertSee('Mum')
            ->callAction(TestAction::make('sendTestMessage')->schemaComponent('notifications', schema: 'form'))
            ->assertNotified('Test message sent to everyone on the list');

        Http::assertSent(fn (Request $r) => str_contains($r['text'] ?? '', 'পরীক্ষামূলক বার্তা'));
    }
}
