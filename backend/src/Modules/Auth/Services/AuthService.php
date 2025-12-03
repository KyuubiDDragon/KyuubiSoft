<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Core\Exceptions\AuthException;
use App\Core\Security\JwtManager;
use App\Core\Security\PasswordHasher;
use App\Core\Security\RbacManager;
use App\Modules\Auth\DTOs\LoginRequest;
use App\Modules\Auth\DTOs\RegisterRequest;
use App\Modules\Auth\Repositories\UserRepository;
use App\Modules\Auth\Repositories\RefreshTokenRepository;
use PragmaRX\Google2FA\Google2FA;
use Ramsey\Uuid\Uuid;

class AuthService
{
    private Google2FA $google2fa;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly JwtManager $jwtManager,
        private readonly PasswordHasher $passwordHasher,
        private readonly RbacManager $rbacManager
    ) {
        $this->google2fa = new Google2FA();
    }

    public function register(RegisterRequest $request): array
    {
        // Check if email already exists
        if ($this->userRepository->findByEmail($request->email)) {
            throw new AuthException('Email already registered');
        }

        // Validate password strength
        $passwordErrors = $this->passwordHasher->validateStrength($request->password);
        if (!empty($passwordErrors)) {
            throw new AuthException(implode(', ', $passwordErrors));
        }

        // Create user
        $userId = Uuid::uuid4()->toString();
        $passwordHash = $this->passwordHasher->hash($request->password);

        $user = $this->userRepository->create([
            'id' => $userId,
            'email' => $request->email,
            'username' => $request->username ?? explode('@', $request->email)[0],
            'password_hash' => $passwordHash,
            'is_active' => 1,
            'is_verified' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Assign default role
        $this->rbacManager->assignRole($userId, 'user');

        // Generate tokens
        $roles = $this->rbacManager->getUserRoles($userId);
        $permissions = $this->rbacManager->getUserPermissions($userId);

        $accessToken = $this->jwtManager->generateAccessToken(
            $userId,
            $request->email,
            $roles,
            $permissions
        );

        $refreshData = $this->jwtManager->generateRefreshToken($userId);
        $this->refreshTokenRepository->store($userId, $refreshData['token_id'], $refreshData['expires_at']);

        // Add roles and permissions to user object
        $user['roles'] = $roles;
        $user['permissions'] = $permissions;

        return [
            'user' => $this->sanitizeUser($user),
            'access_token' => $accessToken,
            'refresh_token' => $refreshData['token'],
            'expires_in' => $this->jwtManager->getAccessTtl(),
        ];
    }

    public function login(LoginRequest $request): array
    {
        // Find user by email or username
        $user = $this->userRepository->findByEmailOrUsername($request->login);

        if (!$user) {
            throw new AuthException('Invalid credentials');
        }

        if (!$this->passwordHasher->verify($request->password, $user['password_hash'])) {
            throw new AuthException('Invalid credentials');
        }

        if (!$user['is_active']) {
            throw new AuthException('Account is disabled');
        }

        // Check 2FA if enabled
        if (!empty($user['two_factor_secret'])) {
            if (empty($request->twoFactorCode)) {
                return [
                    'requires_2fa' => true,
                    'message' => 'Two-factor authentication required',
                ];
            }

            if (!$this->verify2FACode($user['two_factor_secret'], $request->twoFactorCode)) {
                throw new AuthException('Invalid 2FA code');
            }
        }

        // Update last login
        $this->userRepository->update($user['id'], [
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);

        // Rehash password if needed
        if ($this->passwordHasher->needsRehash($user['password_hash'])) {
            $this->userRepository->update($user['id'], [
                'password_hash' => $this->passwordHasher->hash($request->password),
            ]);
        }

        // Generate tokens
        $roles = $this->rbacManager->getUserRoles($user['id']);
        $permissions = $this->rbacManager->getUserPermissions($user['id']);

        $accessToken = $this->jwtManager->generateAccessToken(
            $user['id'],
            $user['email'],
            $roles,
            $permissions
        );

        $refreshData = $this->jwtManager->generateRefreshToken($user['id']);
        $this->refreshTokenRepository->store($user['id'], $refreshData['token_id'], $refreshData['expires_at']);

        // Add roles and permissions to user object
        $user['roles'] = $roles;
        $user['permissions'] = $permissions;

        return [
            'user' => $this->sanitizeUser($user),
            'access_token' => $accessToken,
            'refresh_token' => $refreshData['token'],
            'expires_in' => $this->jwtManager->getAccessTtl(),
        ];
    }

    public function refresh(string $refreshToken): array
    {
        try {
            $payload = $this->jwtManager->validateRefreshToken($refreshToken);
        } catch (\Exception $e) {
            throw new AuthException('Invalid refresh token');
        }

        // Verify token exists in database (not revoked)
        if (!$this->refreshTokenRepository->isValid($payload->sub, $payload->jti)) {
            throw new AuthException('Refresh token has been revoked');
        }

        $user = $this->userRepository->findById($payload->sub);
        if (!$user || !$user['is_active']) {
            throw new AuthException('User not found or inactive');
        }

        // Revoke old refresh token
        $this->refreshTokenRepository->revoke($payload->jti);

        // Generate new tokens
        $roles = $this->rbacManager->getUserRoles($user['id']);
        $permissions = $this->rbacManager->getUserPermissions($user['id']);

        $accessToken = $this->jwtManager->generateAccessToken(
            $user['id'],
            $user['email'],
            $roles,
            $permissions
        );

        $refreshData = $this->jwtManager->generateRefreshToken($user['id']);
        $this->refreshTokenRepository->store($user['id'], $refreshData['token_id'], $refreshData['expires_at']);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshData['token'],
            'expires_in' => $this->jwtManager->getAccessTtl(),
        ];
    }

    public function logout(string $userId, ?string $refreshToken = null): void
    {
        if ($refreshToken) {
            try {
                $payload = $this->jwtManager->validateRefreshToken($refreshToken);
                $this->refreshTokenRepository->revoke($payload->jti);
            } catch (\Exception $e) {
                // Ignore invalid tokens during logout
            }
        } else {
            // Revoke all refresh tokens for user
            $this->refreshTokenRepository->revokeAllForUser($userId);
        }
    }

    public function getUser(string $userId): ?array
    {
        $user = $this->userRepository->findById($userId);

        if (!$user) {
            return null;
        }

        $user['roles'] = $this->rbacManager->getUserRoles($userId);
        $user['permissions'] = $this->rbacManager->getUserPermissions($userId);

        return $this->sanitizeUser($user);
    }

    public function sendPasswordResetEmail(string $email): void
    {
        $user = $this->userRepository->findByEmail($email);

        if (!$user) {
            return; // Don't reveal if email exists
        }

        // Generate reset token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $this->userRepository->storePasswordResetToken($user['id'], $token, $expiresAt);

        // TODO: Send email with reset link
        // For now, just log it
        error_log("Password reset token for {$email}: {$token}");
    }

    public function resetPassword(string $token, string $password): void
    {
        $reset = $this->userRepository->findPasswordResetToken($token);

        if (!$reset || strtotime($reset['expires_at']) < time()) {
            throw new AuthException('Invalid or expired reset token');
        }

        // Validate password strength
        $passwordErrors = $this->passwordHasher->validateStrength($password);
        if (!empty($passwordErrors)) {
            throw new AuthException(implode(', ', $passwordErrors));
        }

        // Update password
        $this->userRepository->update($reset['user_id'], [
            'password_hash' => $this->passwordHasher->hash($password),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Delete reset token
        $this->userRepository->deletePasswordResetToken($token);

        // Revoke all refresh tokens
        $this->refreshTokenRepository->revokeAllForUser($reset['user_id']);
    }

    public function enable2FA(string $userId): array
    {
        // Generate secret
        $secret = $this->generate2FASecret();

        // Store temporarily (not activated yet)
        $this->userRepository->update($userId, [
            'two_factor_temp_secret' => $secret,
        ]);

        $user = $this->userRepository->findById($userId);

        return [
            'secret' => $secret,
            'qr_code_url' => $this->get2FAQrCodeUrl($user['email'], $secret),
        ];
    }

    public function verify2FA(string $userId, string $code): array
    {
        $user = $this->userRepository->findById($userId);

        if (empty($user['two_factor_temp_secret'])) {
            throw new AuthException('2FA not initiated');
        }

        if (!$this->verify2FACode($user['two_factor_temp_secret'], $code)) {
            throw new AuthException('Invalid verification code');
        }

        // Activate 2FA
        $this->userRepository->update($userId, [
            'two_factor_secret' => $user['two_factor_temp_secret'],
            'two_factor_temp_secret' => null,
        ]);

        // Generate backup codes
        $backupCodes = $this->generateBackupCodes();
        $this->userRepository->storeBackupCodes($userId, $backupCodes);

        return [
            'backup_codes' => $backupCodes,
        ];
    }

    public function disable2FA(string $userId, string $code): void
    {
        $user = $this->userRepository->findById($userId);

        if (empty($user['two_factor_secret'])) {
            throw new AuthException('2FA is not enabled');
        }

        if (!$this->verify2FACode($user['two_factor_secret'], $code)) {
            throw new AuthException('Invalid verification code');
        }

        $this->userRepository->update($userId, [
            'two_factor_secret' => null,
        ]);

        $this->userRepository->deleteBackupCodes($userId);
    }

    private function sanitizeUser(array $user): array
    {
        unset(
            $user['password_hash'],
            $user['two_factor_secret'],
            $user['two_factor_temp_secret']
        );

        return $user;
    }

    private function generate2FASecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    private function verify2FACode(string $secret, string $code): bool
    {
        // Debug logging
        error_log("2FA LIB DEBUG: Secret: " . $secret . ", Code: " . $code);
        error_log("2FA LIB DEBUG: Server timestamp: " . time());

        try {
            // Calculate what the current code SHOULD be
            $currentCode = $this->google2fa->getCurrentOtp($secret);
            error_log("2FA LIB DEBUG: Expected current code: " . $currentCode);

            // Window of 1 allows for 30 seconds clock drift in either direction
            $result = $this->google2fa->verifyKey($secret, $code, 1);
            error_log("2FA LIB DEBUG: Verify result: " . ($result ? 'true' : 'false'));
            return $result;
        } catch (\Throwable $e) {
            error_log("2FA LIB ERROR: " . $e->getMessage());
            error_log("2FA LIB ERROR TRACE: " . $e->getTraceAsString());
            return false;
        }
    }

    private function get2FAQrCodeUrl(string $email, string $secret): string
    {
        $issuer = 'KyuubiSoft';
        // Return otpauth URL - QR code will be generated in frontend
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            urlencode($issuer),
            urlencode($email),
            $secret,
            urlencode($issuer)
        );
    }

    private function generateBackupCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < 10; $i++) {
            $codes[] = bin2hex(random_bytes(4));
        }

        return $codes;
    }
}
