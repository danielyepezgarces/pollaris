<?php

namespace App\Tests\Service;

use App\Service\TelegramService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class TelegramServiceTest extends TestCase
{
    public function testNotConfiguredReturnsFalse(): void
    {
        $httpClient = new MockHttpClient();
        $service = new TelegramService($httpClient, new NullLogger(), null);

        $this->assertFalse($service->isConfigured());
        $this->assertFalse($service->sendMessage('12345', 'Hello'));
    }

    public function testSendMessageSuccess(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['ok' => true, 'result' => ['message_id' => 101]])),
        ]);

        $service = new TelegramService($httpClient, new NullLogger(), 'fake-token-123');

        $this->assertTrue($service->isConfigured());
        $this->assertTrue($service->sendMessage('-1001234567890', '<b>Test Message</b>'));
    }

    public function testSendMessageHttpError(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['ok' => false, 'description' => 'Chat not found']), ['http_code' => 400]),
        ]);

        $service = new TelegramService($httpClient, new NullLogger(), 'fake-token-123');
        $this->assertFalse($service->sendMessage('invalid-chat', 'Hello'));
    }
}
