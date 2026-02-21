<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Modules\AI\Services\AIService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;

class AIServiceTest extends TestCase
{
    private AIService $service;
    private MockObject $db;

    protected function setUp(): void
    {
        $_ENV['APP_KEY'] = 'test-app-key-for-unit-tests';

        $this->db = $this->createMock(Connection::class);
        $this->service = new AIService($this->db);
    }

    // ─── Encryption key derivation ────────────────────────────────────────────

    public function testEncryptionKeyIsExactly32Bytes(): void
    {
        $reflection = new ReflectionClass($this->service);
        $property = $reflection->getProperty('encryptionKey');
        $property->setAccessible(true);

        $key = $property->getValue($this->service);

        $this->assertEquals(32, strlen($key), 'AES-256 key must be exactly 32 bytes (SHA-256 raw binary)');
    }

    public function testEncryptionKeyIsDerivedFromAppKey(): void
    {
        // The key should be hash('sha256', APP_KEY, true), not the raw APP_KEY
        $reflection = new ReflectionClass($this->service);
        $property = $reflection->getProperty('encryptionKey');
        $property->setAccessible(true);

        $key = $property->getValue($this->service);
        $expected = hash('sha256', 'test-app-key-for-unit-tests', true);

        $this->assertEquals($expected, $key);
    }

    public function testEncryptionKeyIsNotRawAppKey(): void
    {
        $reflection = new ReflectionClass($this->service);
        $property = $reflection->getProperty('encryptionKey');
        $property->setAccessible(true);

        $key = $property->getValue($this->service);

        // Must NOT be the raw string – that was the old insecure behaviour
        $this->assertNotEquals('test-app-key-for-unit-tests', $key);
    }

    // ─── API key encryption round-trip ────────────────────────────────────────

    public function testEncryptAndDecryptApiKeyRoundTrip(): void
    {
        $reflection = new ReflectionClass($this->service);

        $encrypt = $reflection->getMethod('encryptApiKey');
        $encrypt->setAccessible(true);

        $decrypt = $reflection->getMethod('decryptApiKey');
        $decrypt->setAccessible(true);

        $originalKey = 'sk-test-api-key-1234567890abcdef';

        $encrypted = $encrypt->invoke($this->service, $originalKey);
        $decrypted = $decrypt->invoke($this->service, $encrypted);

        $this->assertNotEquals($originalKey, $encrypted, 'Encrypted value must differ from plaintext');
        $this->assertEquals($originalKey, $decrypted, 'Decrypted value must match original');
    }

    public function testEncryptApiKeyProducesBase64Output(): void
    {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('encryptApiKey');
        $method->setAccessible(true);

        $encrypted = $method->invoke($this->service, 'test-key');

        // base64_decode should succeed and produce binary data
        $decoded = base64_decode($encrypted, true);
        $this->assertNotFalse($decoded, 'encryptApiKey output must be valid base64');
        // IV (16 bytes) + ciphertext
        $this->assertGreaterThan(16, strlen($decoded), 'Decoded data must contain IV + ciphertext');
    }

    public function testEncryptApiKeyProducesUniqueOutputEachTime(): void
    {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('encryptApiKey');
        $method->setAccessible(true);

        $key = 'same-api-key';
        $enc1 = $method->invoke($this->service, $key);
        $enc2 = $method->invoke($this->service, $key);

        // Each encryption uses a random IV, so ciphertext must differ
        $this->assertNotEquals($enc1, $enc2, 'Each encryption call must produce a unique ciphertext (random IV)');
    }

    // ─── Provider registry ────────────────────────────────────────────────────

    public function testProvidersAreRegistered(): void
    {
        $reflection = new ReflectionClass($this->service);
        $property = $reflection->getProperty('providers');
        $property->setAccessible(true);

        $providers = $property->getValue($this->service);

        $expected = ['openai', 'openrouter', 'anthropic', 'ollama', 'custom'];
        foreach ($expected as $name) {
            $this->assertArrayHasKey($name, $providers, "Provider '{$name}' must be registered");
        }
    }

    // ─── isConfigured ─────────────────────────────────────────────────────────

    public function testIsConfiguredReturnsFalseWhenNoSettings(): void
    {
        $this->db->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        $result = $this->service->isConfigured('user-123');
        $this->assertFalse($result);
    }

    public function testIsConfiguredReturnsTrueWhenEncryptedKeyExists(): void
    {
        $this->db->expects($this->once())
            ->method('fetchOne')
            ->willReturn('some-encrypted-value');

        $result = $this->service->isConfigured('user-123');
        $this->assertTrue($result);
    }

    public function testIsConfiguredReturnsFalseWhenKeyIsEmpty(): void
    {
        $this->db->expects($this->once())
            ->method('fetchOne')
            ->willReturn('');

        $result = $this->service->isConfigured('user-123');
        $this->assertFalse($result);
    }
}
