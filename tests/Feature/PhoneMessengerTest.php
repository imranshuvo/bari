<?php

namespace Tests\Feature;

use App\Enums\NotificationChannel;
use App\Support\PhoneMessenger;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PhoneMessengerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.waha.url' => 'http://waha.test',
            'services.waha.key' => 'secret',
            'services.sms_gate.url' => 'https://sms.test/3rdparty/v1',
            'services.sms_gate.username' => 'user',
            'services.sms_gate.password' => 'pass',
        ]);
    }

    public function test_it_sends_on_whatsapp_first(): void
    {
        Http::fake(['waha.test/*' => Http::response(['id' => 'ok']), 'sms.test/*' => Http::response(['id' => 'ok'])]);

        $this->assertSame(NotificationChannel::WhatsApp, PhoneMessenger::send('01712345678', 'Hello'));

        Http::assertSent(fn (Request $r) => $r->url() === 'http://waha.test/api/sendText'
            && $r['chatId'] === '8801712345678@c.us' && $r['text'] === 'Hello');
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'sms.test'));
    }

    public function test_it_falls_back_to_sms_when_whatsapp_fails(): void
    {
        Http::fake(['waha.test/*' => Http::response('down', 500), 'sms.test/*' => Http::response(['id' => 'ok'])]);

        $this->assertSame(NotificationChannel::Sms, PhoneMessenger::send('+8801712345678', 'Hello'));

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'sms.test') && $r['phoneNumbers'] === ['+8801712345678']);
    }

    public function test_it_returns_null_when_everything_fails(): void
    {
        Http::fake(['*' => Http::response('down', 500)]);

        $this->assertNull(PhoneMessenger::send('+8801712345678', 'Hello'));
    }

    public function test_nothing_is_available_without_whatsapp_or_sms(): void
    {
        config(['services.waha.url' => null, 'services.sms_gate.username' => null, 'services.sms_gate.password' => null]);
        Http::fake();

        $this->assertFalse(PhoneMessenger::isAvailable());
        $this->assertNull(PhoneMessenger::send('+8801712345678', 'Hello'));
        Http::assertNothingSent();
    }

    public function test_sms_alone_is_enough(): void
    {
        config(['services.waha.url' => null]);
        Http::fake(['sms.test/*' => Http::response(['id' => 'ok'])]);

        $this->assertTrue(PhoneMessenger::isAvailable());
        $this->assertSame(NotificationChannel::Sms, PhoneMessenger::send('+8801712345678', 'Hello'));
    }
}
