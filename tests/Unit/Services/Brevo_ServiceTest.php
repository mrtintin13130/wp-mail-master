<?php

namespace Martho\WpMailMaster\Tests\Unit\Services;

use Martho\WpMailMaster\Services\Brevo_Service;
use Martho\WpMailMaster\WP_Mail_Master_Exception;
use PHPUnit\Framework\TestCase;
use WP_Mock;
use Brevo\Client\Api\TransactionalEmailsApi;
use Brevo\Client\Configuration;
use Brevo\Client\Model\SendSmtpEmail;

class Brevo_ServiceTest extends TestCase
{
    private $brevoService;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        $this->brevoService = new Brevo_Service([
            'brevo_api_key' => 'api-key',
            'from_email' => 'php@unit.test',
            'from_name' => 'Test Sender'
        ]);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    public function testValidateConfigWithValidApiKey()
    {
        $this->assertTrue($this->brevoService->validateConfig());
    }

    public function testValidateConfigWithEmptyApiKey()
    {
        $this->expectException(WP_Mail_Master_Exception::class);
        $this->expectExceptionCode(WP_Mail_Master_Exception::API_KEY_ERROR);

        $brevoService = new Brevo_Service(['brevo_api_key' => '']);
        $brevoService->validateConfig();
    }

    public function testSendWithInvalidEmail()
    {
        $this->expectException(WP_Mail_Master_Exception::class);
        $this->expectExceptionCode(WP_Mail_Master_Exception::INVALID_EMAIL_ERROR);

        $this->brevoService->send(
            'invalid-email',
            'Test Subject',
            'Test Message'
        );
    }

    public function testSendWithValidEmail()
    {
        $mockApiInstance = $this->createMock(TransactionalEmailsApi::class);
        $mockApiInstance->expects($this->once())
            ->method('sendTransacEmail')
            ->willReturn(true);

        $reflection = new \ReflectionClass($this->brevoService);
        $property = $reflection->getProperty('apiInstance');
        $property->setAccessible(true);
        $property->setValue($this->brevoService, $mockApiInstance);

        $result = $this->brevoService->send(
            'test@example.com',
            'Test Subject',
            'Test Message'
        );

        $this->assertTrue($result);
    }

    public function testSendWithAttachments()
    {
        $mockApiInstance = $this->createMock(TransactionalEmailsApi::class);
        $mockApiInstance->expects($this->once())
            ->method('sendTransacEmail')
            ->willReturn(true);

        $reflection = new \ReflectionClass($this->brevoService);
        $property = $reflection->getProperty('apiInstance');
        $property->setAccessible(true);
        $property->setValue($this->brevoService, $mockApiInstance);

        // Create temp test file
        $tempFile = tmpfile();
        $tempFilePath = stream_get_meta_data($tempFile)['uri'];
        fwrite($tempFile, 'Test content');

        $result = $this->brevoService->send(
            'test@example.com',
            'Test Subject',
            'Test Message',
            '',
            [$tempFilePath]
        );

        $this->assertTrue($result);
        fclose($tempFile);
    }

    public function testAttachFileMethodThrowsException()
    {
        $this->expectException(WP_Mail_Master_Exception::class);
        $this->expectExceptionCode(WP_Mail_Master_Exception::SERVICE_ERROR);

        $reflection = new \ReflectionClass($this->brevoService);
        $method = $reflection->getMethod('attachFile');
        $method->setAccessible(true);

        $method->invokeArgs($this->brevoService, [
            null,
            'test.txt',
            'content',
            'text/plain'
        ]);
    }
}
