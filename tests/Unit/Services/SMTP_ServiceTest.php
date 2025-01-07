<?php

namespace Martho\WpMailMaster\Tests\Unit\Services;

use Martho\WpMailMaster\Services\SMTP_Service;
use Martho\WpMailMaster\WP_Mail_Master_Exception;
use PHPUnit\Framework\TestCase;
use PHPMailer\PHPMailer\PHPMailer;
use WP_Mock;

class SMTP_ServiceTest extends TestCase
{
    private $smtpService;
    private $mockMailer;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        // Create a mock PHPMailer instance
        $this->mockMailer = $this->createMock(PHPMailer::class);

        $this->smtpService = new SMTP_Service([
            'smtp_host' => 'test.com',
            'smtp_port' => 587,
            'smtp_username' => 'php@unit.test',
            'smtp_password' => 'password',
            'smtp_encryption' => 'tls',
            'from_email' => 'php@unit.test',
            'from_name' => 'Test Sender'
        ], $this->mockMailer); // Inject the mock
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    public function testValidateConfigWithValidConfig(): void
    {
        $this->assertTrue($this->smtpService->validateConfig());
    }

    public function testValidateConfigWithMissingHost(): void
    {
        $this->expectException(WP_Mail_Master_Exception::class);
        $this->expectExceptionCode(WP_Mail_Master_Exception::INVALID_CONFIG_ERROR);

        $service = new SMTP_Service([
            'smtp_port' => 587,
            'smtp_username' => 'test',
            'smtp_password' => 'test'
        ]);
        $service->validateConfig();
    }

    public function testValidateConfigWithInvalidPort(): void
    {
        $this->expectException(WP_Mail_Master_Exception::class);
        $this->expectExceptionCode(WP_Mail_Master_Exception::VALIDATION_ERROR);

        $service = new SMTP_Service([
            'smtp_host' => 'test.com',
            'smtp_port' => -1,
            'smtp_username' => 'test',
            'smtp_password' => 'test'
        ]);
        $service->validateConfig();
    }

    public function testSendWithInvalidEmail(): void
    {
        $this->expectException(WP_Mail_Master_Exception::class);
        $this->expectExceptionCode(WP_Mail_Master_Exception::INVALID_EMAIL_ERROR);

        $this->smtpService->send(
            'invalid-email',
            'Test Subject',
            'Test Message'
        );
    }

    public function testSendWithValidEmail(): void
    {
        // Configure the mock to expect certain method calls
        $this->mockMailer->expects($this->once())
            ->method('isSMTP');

        $this->mockMailer->expects($this->once())
            ->method('setFrom')
            ->with('php@unit.test', 'Test Sender');

        $this->mockMailer->expects($this->once())
            ->method('addAddress')
            ->with('test@example.com');

        $this->mockMailer->expects($this->once())
            ->method('isHTML')
            ->with(true);

        $this->mockMailer->expects($this->once())
            ->method('send')
            ->willReturn(true);

        $result = $this->smtpService->send(
            'test@example.com',
            'Test Subject',
            'Test Message'
        );

        $this->assertTrue($result);
    }
}
