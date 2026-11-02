<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use App\Support\VapidKeys;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_manifest_describes_the_bari_app(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true);

        $this->assertSame('Bari', $manifest['name']);
        $this->assertSame('Bari', $manifest['short_name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertCount(3, $manifest['icons']);

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
    }

    public function test_the_service_worker_and_offline_page_exist(): void
    {
        $worker = (string) file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("'/offline.html'", $worker);
        $this->assertStringContainsString("addEventListener('push'", $worker);
        $this->assertStringContainsString("addEventListener('notificationclick'", $worker);
        $this->assertStringContainsString('offline', (string) file_get_contents(public_path('offline.html')));
        $this->assertFileExists(public_path('icons/apple-touch-icon.png'));
    }

    public function test_pages_link_the_app_manifest_and_icons(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('rel="manifest" href="/manifest.webmanifest"', escape: false)
            ->assertSee('rel="apple-touch-icon"', escape: false)
            ->assertSee('name="theme-color"', escape: false)
            ->assertSee('/sw.js', escape: false)
            ->assertSee('Bari');
    }

    public function test_signed_in_pages_offer_app_notifications(): void
    {
        Storage::fake('local');
        VapidKeys::forget();
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $project->addMember($user, ProjectRole::Owner);

        $this->actingAs($user)->get(Filament::getPanel('app')->getUrl($project))
            ->assertOk()
            ->assertSee('name="vapid-public-key" content="'.VapidKeys::get()['publicKey'].'"', escape: false)
            ->assertSee('Turn on notifications')
            ->assertSee('Turn off notifications');
    }
}
