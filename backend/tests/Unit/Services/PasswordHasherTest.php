<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Security\PasswordHasher;
use PHPUnit\Framework\TestCase;

class PasswordHasherTest extends TestCase
{
    private PasswordHasher $hasher;

    protected function setUp(): void
    {
        $this->hasher = new PasswordHasher();
    }

    public function testHashProducesArgon2idHash(): void
    {
        $hash = $this->hasher->hash('Password123!A');
        $this->assertStringStartsWith('$argon2id$', $hash);
    }

    public function testVerifyReturnsTrueForCorrectPassword(): void
    {
        $password = 'Password123!A';
        $hash = $this->hasher->hash($password);
        $this->assertTrue($this->hasher->verify($password, $hash));
    }

    public function testVerifyReturnsFalseForWrongPassword(): void
    {
        $hash = $this->hasher->hash('Password123!A');
        $this->assertFalse($this->hasher->verify('wrong-password', $hash));
    }

    public function testHashIsUniqueEachTime(): void
    {
        $password = 'Password123!A';
        $hash1 = $this->hasher->hash($password);
        $hash2 = $this->hasher->hash($password);
        $this->assertNotEquals($hash1, $hash2, 'Argon2id uses random salt per hash');
    }

    // ─── validateStrength ─────────────────────────────────────────────────────

    public function testValidateStrengthPassesForStrongPassword(): void
    {
        $errors = $this->hasher->validateStrength('SecurePass123!');
        $this->assertEmpty($errors);
    }

    public function testValidateStrengthFailsForShortPassword(): void
    {
        $errors = $this->hasher->validateStrength('Short1!A');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('12 characters', $errors[0]);
    }

    public function testValidateStrengthFailsWithoutUppercase(): void
    {
        $errors = $this->hasher->validateStrength('lowercase123!aa');
        $this->assertNotEmpty($errors);
        $this->assertTrue(
            count(array_filter($errors, fn($e) => stripos($e, 'uppercase') !== false)) > 0
        );
    }

    public function testValidateStrengthFailsWithoutLowercase(): void
    {
        $errors = $this->hasher->validateStrength('UPPERCASE123!AA');
        $this->assertTrue(
            count(array_filter($errors, fn($e) => stripos($e, 'lowercase') !== false)) > 0
        );
    }

    public function testValidateStrengthFailsWithoutNumber(): void
    {
        $errors = $this->hasher->validateStrength('NoNumbers!ABCDE');
        $this->assertTrue(
            count(array_filter($errors, fn($e) => stripos($e, 'number') !== false)) > 0
        );
    }

    public function testValidateStrengthFailsWithoutSpecialChar(): void
    {
        $errors = $this->hasher->validateStrength('NoSpecialChars123A');
        $this->assertTrue(
            count(array_filter($errors, fn($e) => stripos($e, 'special') !== false)) > 0
        );
    }

    public function testIsStrongReturnsTrueForStrongPassword(): void
    {
        $this->assertTrue($this->hasher->isStrong('SecurePass123!'));
    }

    public function testIsStrongReturnsFalseForWeakPassword(): void
    {
        $this->assertFalse($this->hasher->isStrong('weak'));
    }
}
