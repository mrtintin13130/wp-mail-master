<?php
// tests/Unit/Services/Mailjet_ServiceTest.php

namespace Martho\WpMailMaster\Tests\Unit\Services;

use Martho\WpMailMaster\Services\Mailjet_Service;
use Martho\WpMailMaster\WP_Mail_Master_Exception;
use Mailjet\Client;
use Mailjet\Resources;
use PHPUnit\Framework\TestCase;
use WP_Mock;

class Mailjet_ServiceTest extends TestCase
{
    /** @var Mailjet_Service */
    private $mailjetService;

    /** @var \PHPUnit\Framework\MockObject\MockObject|Client */
    private $mockClient;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        $options = [
            'mailjet_api_key' => 'fake_api_key',
            'mailjet_secret_key' => 'fake_secret_key',
            'from_email' => 'test@unit.test',
            'from_name' => 'Test Sender'
        ];

        $this->mailjetService = new Mailjet_Service($options, $this->mockClient);

        // Create a mock Mailjet client
        $this->mockClient = $this->createMock(Client::class);

        // Inject the mock client into the Mailjet_Service instance via reflection
        $reflection = new \ReflectionClass($this->mailjetService);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($this->mailjetService, $this->mockClient);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    public function testValidateConfigWithValidKeys(): void
    {
        $this->assertTrue($this->mailjetService->validateConfig());
    }

    public function testValidateConfigMissingKeys(): void
    {
        $this->expectException(WP_Mail_Master_Exception::class);
        $this->expectExceptionCode(WP_Mail_Master_Exception::API_KEY_ERROR);

        $service = new Mailjet_Service([
            'mailjet_api_key' => '',
            'mailjet_secret_key' => ''
        ]);
        $service->validateConfig();
    }

    public function testSendWithValidEmail(): void
    {
        // Mock a Mailjet Response object
        $responseMock = $this->createMock(\Mailjet\Response::class);
        $responseMock->method('success')->willReturn(true);

        // Expect that 'post' is called with the correct resource and parameters
        $this->mockClient->expects($this->once())
            ->method('post')
            ->with(Resources::$Email, $this->callback(function ($params) {
                // Validate that 'From' email and name are set correctly
                return isset($params['body']['Messages'][0]['From']['Email']) &&
                    $params['body']['Messages'][0]['From']['Email'] === 'test@unit.test' &&
                    $params['body']['Messages'][0]['From']['Name'] === 'Test Sender' &&
                    $params['body']['Messages'][0]['To'][0]['Email'] === 'recipient@example.com';
            }))
            ->willReturn($responseMock);

        $result = $this->mailjetService->send('recipient@example.com', 'Test Subject', '<p>HTML Content</p>');
        $this->assertTrue($result);
    }

    public function testSendWithInvalidEmail(): void
    {
        $this->expectException(WP_Mail_Master_Exception::class);
        $this->expectExceptionCode(WP_Mail_Master_Exception::VALIDATION_ERROR);

        $this->mailjetService->send('invalid-email', 'Test Subject', 'Test Message');
    }

    public function testSendApiError(): void
    {
        $responseMock = $this->createMock(\Mailjet\Response::class);
        $responseMock->method('success')->willReturn(false);
        $responseMock->method('getData')->willReturn(['ErrorMessage' => 'Invalid credentials']);

        $this->mockClient->method('post')->willReturn($responseMock);

        $this->expectException(WP_Mail_Master_Exception::class);
        $this->expectExceptionCode(WP_Mail_Master_Exception::API_ERROR);

        $this->mailjetService->send('recipient@example.com', 'Test Subject', 'Test Message');
    }
}
