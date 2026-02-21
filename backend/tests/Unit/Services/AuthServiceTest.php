<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Exceptions\AuthException;
use App\Core\Security\JwtManager;
use App\Core\Security\PasswordHasher;
use App\Core\Security\RbacManager;
use App\Modules\Auth\DTOs\LoginRequest;
use App\Modules\Auth\DTOs\RegisterRequest;
use App\Modules\Auth\Repositories\RefreshTokenRepository;
use App\Modules\Auth\Repositories\UserRepository;
use App\Modules\Auth\Services\AuthService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AuthServiceTest extends TestCase
{
    private AuthService $service;
    private MockObject $userRepository;
    private MockObject $refreshTokenRepository;
    private MockObject $jwtManager;
    private PasswordHasher $passwordHasher;
    private MockObject $rbacManager;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->refreshTokenRepository = $this->createMock(RefreshTokenRepository::class);
        $this->jwtManager = $this->createMock(JwtManager::class);
        $this->passwordHasher = new PasswordHasher();
        $this->rbacManager = $this->createMock(RbacManager::class);

        $this->service = new AuthService(
            $this->userRepository,
            $this->refreshTokenRepository,
            $this->jwtManager,
            $this->passwordHasher,
            $this->rbacManager
        );
    }

    // ─── register ─────────────────────────────────────────────────────────────

    public function testRegisterThrowsWhenEmailAlreadyExists(): void
    {
        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->with('existing@example.com')
            ->willReturn(['id' => 'user-1', 'email' => 'existing@example.com']);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Email already registered');

        $this->service->register(new RegisterRequest(
            email: 'existing@example.com',
            password: 'Password123!@#',
            passwordConfirmation: 'Password123!@#'
        ));
    }

    public function testRegisterThrowsWhenPasswordTooWeak(): void
    {
        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->willReturn(null);

        $this->expectException(AuthException::class);

        $this->service->register(new RegisterRequest(
            email: 'new@example.com',
            password: 'weak',
            passwordConfirmation: 'weak'
        ));
    }

    public function testRegisterSuccessReturnsPendingApproval(): void
    {
        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->willReturn(null);

        $this->userRepository->expects($this->once())
            ->method('create')
            ->willReturn([
                'id' => 'new-uuid',
                'email' => 'new@example.com',
                'username' => 'new',
                'is_active' => 0,
            ]);

        $this->rbacManager->expects($this->once())
            ->method('assignRole')
            ->with($this->isType('string'), 'pending');

        $result = $this->service->register(new RegisterRequest(
            email: 'new@example.com',
            password: 'ValidPassword123!',
            passwordConfirmation: 'ValidPassword123!'
        ));

        $this->assertTrue($result['pending_approval']);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('user', $result);
    }

    // ─── login ────────────────────────────────────────────────────────────────

    public function testLoginThrowsWhenUserNotFound(): void
    {
        $this->userRepository->expects($this->once())
            ->method('findByEmailOrUsername')
            ->willReturn(null);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Invalid credentials');

        $this->service->login(new LoginRequest('unknown@example.com', 'password'));
    }

    public function testLoginThrowsWhenPasswordWrong(): void
    {
        $hash = $this->passwordHasher->hash('correct-password-123!A');

        $this->userRepository->expects($this->once())
            ->method('findByEmailOrUsername')
            ->willReturn([
                'id' => 'user-1',
                'email' => 'user@example.com',
                'password_hash' => $hash,
                'is_active' => 1,
                'two_factor_secret' => null,
            ]);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Invalid credentials');

        $this->service->login(new LoginRequest('user@example.com', 'wrong-password'));
    }

    public function testLoginThrowsWhenAccountInactive(): void
    {
        $hash = $this->passwordHasher->hash('Password123!A');

        $this->userRepository->expects($this->once())
            ->method('findByEmailOrUsername')
            ->willReturn([
                'id' => 'user-1',
                'email' => 'user@example.com',
                'password_hash' => $hash,
                'is_active' => 0,
                'two_factor_secret' => null,
            ]);

        $this->rbacManager->expects($this->once())
            ->method('getUserRoles')
            ->willReturn(['pending']);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessageMatches('/freigeschaltet|approved/i');

        $this->service->login(new LoginRequest('user@example.com', 'Password123!A'));
    }

    public function testLoginReturnsTwoFactorRequiredWhen2FAIsSet(): void
    {
        $hash = $this->passwordHasher->hash('Password123!A');

        $this->userRepository->expects($this->once())
            ->method('findByEmailOrUsername')
            ->willReturn([
                'id' => 'user-1',
                'email' => 'user@example.com',
                'password_hash' => $hash,
                'is_active' => 1,
                'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            ]);

        $result = $this->service->login(new LoginRequest('user@example.com', 'Password123!A'));

        $this->assertTrue($result['requires_2fa']);
    }
}
