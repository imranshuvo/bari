<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Category;
use App\Models\Cost;
use App\Models\Project;
use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\CostChanged;
use App\Services\PushService;
use App\Support\VapidKeys;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebPushTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private Category $roof;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::factory()->create(['name' => 'Our new house', 'currency' => 'BDT']);
        $this->roof = Category::factory()->for($this->project)->create(['name' => 'Roof']);
    }

    private function member(ProjectRole $role = ProjectRole::Editor, string $locale = 'en', ?Project $project = null): User
    {
        $user = User::factory()->create(['locale' => $locale]);
        ($project ?? $this->project)->addMember($user, $role);

        return $user;
    }

    private function subscribe(User $user, string $endpoint = 'https://push.example.com/abc'): PushSubscription
    {
        return $user->pushSubscriptions()->create(['endpoint' => $endpoint, 'public_key' => 'p256dh-key', 'auth_token' => 'auth-key']);
    }

    public function test_a_signed_in_person_turns_notifications_on_and_off(): void
    {
        $rina = $this->member();
        $body = ['endpoint' => 'https://push.example.com/rina', 'keys' => ['p256dh' => 'p-key', 'auth' => 'a-key']];

        $this->actingAs($rina)->postJson('/push-subscriptions', $body)->assertNoContent();
        $this->actingAs($rina)->postJson('/push-subscriptions', $body)->assertNoContent();

        $subscription = PushSubscription::sole();
        $this->assertTrue($subscription->user->is($rina));
        $this->assertSame('p-key', $subscription->public_key);

        $this->actingAs($rina)->deleteJson('/push-subscriptions', ['endpoint' => 'https://push.example.com/rina'])->assertNoContent();
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_guests_cannot_subscribe(): void
    {
        $this->postJson('/push-subscriptions', ['endpoint' => 'https://push.example.com/x', 'keys' => ['p256dh' => 'p', 'auth' => 'a']])
            ->assertUnauthorized();
    }

    public function test_other_members_get_a_push_but_not_the_person_who_made_the_change(): void
    {
        Notification::fake();
        $actor = $this->member();
        $partner = $this->member(ProjectRole::Viewer, 'bn');
        $noDevice = $this->member();
        $this->subscribe($actor, 'https://push.example.com/actor');
        $this->subscribe($partner);

        $this->actingAs($actor);
        Cost::factory()->in($this->roof)->create();

        Notification::assertSentTo($partner, CostChanged::class, fn (CostChanged $n, array $channels) => $channels === [WebPushChannel::class] && $n->locale === 'bn');
        Notification::assertNotSentTo($actor, CostChanged::class);
        Notification::assertNotSentTo($noDevice, CostChanged::class);
    }

    public function test_push_only_reaches_members_of_that_project(): void
    {
        Notification::fake();
        $other = Project::factory()->create();
        $outsider = $this->member(project: $other);
        $this->subscribe($outsider);
        $this->actingAs($this->member());

        Cost::factory()->in($this->roof)->create();

        Notification::assertNotSentTo($outsider, CostChanged::class);
    }

    public function test_the_push_has_the_project_headline_and_a_link_to_spent(): void
    {
        Notification::fake();
        $partner = $this->member();
        $this->subscribe($partner);
        $this->actingAs($this->member());

        Cost::factory()->in($this->roof)->create(['amount' => 25_000_00]);

        Notification::assertSentTo($partner, CostChanged::class, function (CostChanged $n) use ($partner) {
            $push = $n->toWebPush($partner);

            return $push['title'] === 'Our new house'
                && str_contains($push['body'], 'added a cost')
                && str_contains($push['body'], '25,000')
                && str_contains($push['url'], '/spent');
        });
    }

    public function test_a_subscription_the_push_service_says_is_gone_is_removed(): void
    {
        $partner = $this->member();
        $gone = $this->subscribe($partner, 'https://push.example.com/gone');
        $kept = $this->subscribe($partner, 'https://push.example.com/kept');

        $this->app->instance(PushService::class, new class extends PushService
        {
            public function send(PushSubscription $subscription, array $payload): bool
            {
                return $subscription->endpoint !== 'https://push.example.com/gone';
            }
        });

        (new WebPushChannel)->send($partner, new CostChanged([
            'event' => 'created', 'project' => 'Our new house', 'currency' => 'BDT', 'actor' => 'Imran', 'amount' => 100,
            'supplier' => null, 'description' => null, 'category' => 'Roof', 'is_paid' => true, 'due_date' => null,
            'changes' => [], 'url' => 'https://bari.test/1/costs',
        ]));

        $this->assertModelMissing($gone);
        $this->assertModelExists($kept);
    }

    public function test_vapid_keys_are_made_once_and_reused(): void
    {
        Storage::fake('local');
        VapidKeys::forget();
        config(['services.webpush.public_key' => null, 'services.webpush.private_key' => null]);

        $first = VapidKeys::get();
        VapidKeys::forget();
        $second = VapidKeys::get();

        $this->assertNotEmpty($first['publicKey']);
        $this->assertSame($first, $second);
        Storage::disk('local')->assertExists('vapid.json');

        config(['services.webpush.public_key' => 'env-public', 'services.webpush.private_key' => 'env-private']);
        VapidKeys::forget();
        $this->assertSame(['publicKey' => 'env-public', 'privateKey' => 'env-private'], VapidKeys::get());
    }

    public function test_a_shared_device_moves_to_whoever_signs_in(): void
    {
        $body = ['endpoint' => 'https://push.example.com/family-phone', 'keys' => ['p256dh' => 'p', 'auth' => 'a']];
        $imran = $this->member();
        $rina = $this->member();

        $this->actingAs($imran)->postJson('/push-subscriptions', $body)->assertNoContent();
        $this->actingAs($rina)->postJson('/push-subscriptions', $body)->assertNoContent();

        $this->assertTrue(PushSubscription::sole()->user->is($rina));
    }

    public function test_signing_out_stops_notifications_on_that_device(): void
    {
        $rina = $this->member();

        $this->actingAs($rina)->postJson('/push-subscriptions', ['endpoint' => 'https://push.example.com/rina', 'keys' => ['p256dh' => 'p', 'auth' => 'a']]);
        $this->actingAs($rina)->post(Filament::getPanel('app')->getLogoutUrl());

        $this->assertSame(0, PushSubscription::count());
    }
}
