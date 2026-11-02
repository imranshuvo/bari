<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
