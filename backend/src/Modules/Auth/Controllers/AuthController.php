<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Core\Exceptions\AuthException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\DTOs\LoginRequest;
use App\Modules\Auth\DTOs\RegisterRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AuthController
{
    public function __construct(
        private readonly AuthService $authService
    ) {}

    public function register(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody() ?? [];

        $registerRequest = new RegisterRequest(
            email: $data['email'] ?? '',
            password: $data['password'] ?? '',
            passwordConfirmation: $data['password_confirmation'] ?? '',
            username: $data['username'] ?? null
        );

        $errors = $registerRequest->validate();
        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }

        $result = $this->authService->register($registerRequest);

        return JsonResponse::created($result, 'Registration successful');
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody() ?? [];

        $loginRequest = new LoginRequest(
            email: $data['email'] ?? '',
            password: $data['password'] ?? '',
            twoFactorCode: $data['two_factor_code'] ?? null
        );

        $errors = $loginRequest->validate();
        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }

        $result = $this->authService->login($loginRequest);

        return JsonResponse::success($result, 'Login successful');
    }

    public function refresh(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody() ?? [];
        $refreshToken = $data['refresh_token'] ?? '';

        if (empty($refreshToken)) {
            throw new ValidationException('Refresh token is required');
        }

        $result = $this->authService->refresh($refreshToken);

        return JsonResponse::success($result, 'Token refreshed');
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];
        $refreshToken = $data['refresh_token'] ?? null;

        $this->authService->logout($userId, $refreshToken);

        return JsonResponse::success(null, 'Logged out successfully');
    }

    public function me(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $user = $this->authService->getUser($userId);

        if (!$user) {
            throw new AuthException('User not found');
        }

        return JsonResponse::success($user);
    }

    public function forgotPassword(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody() ?? [];
        $email = $data['email'] ?? '';

        if (empty($email)) {
            throw new ValidationException('Email is required');
        }

        // Always return success to prevent email enumeration
        $this->authService->sendPasswordResetEmail($email);

        return JsonResponse::success(null, 'If the email exists, a password reset link has been sent');
    }

    public function resetPassword(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody() ?? [];

        $token = $data['token'] ?? '';
        $password = $data['password'] ?? '';
        $passwordConfirmation = $data['password_confirmation'] ?? '';

        if (empty($token) || empty($password)) {
            throw new ValidationException('Token and password are required');
        }

        if ($password !== $passwordConfirmation) {
            throw new ValidationException('Passwords do not match');
        }

        $this->authService->resetPassword($token, $password);

        return JsonResponse::success(null, 'Password reset successful');
    }

    public function enable2FA(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $result = $this->authService->enable2FA($userId);

        return JsonResponse::success($result, '2FA setup initiated');
    }

    public function verify2FA(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];
        $code = $data['code'] ?? '';

        if (empty($code)) {
            throw new ValidationException('Verification code is required');
        }

        $result = $this->authService->verify2FA($userId, $code);

        return JsonResponse::success($result, '2FA enabled successfully');
    }

    public function disable2FA(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];
        $code = $data['code'] ?? '';

        if (empty($code)) {
            throw new ValidationException('Verification code is required');
        }

        $this->authService->disable2FA($userId, $code);

        return JsonResponse::success(null, '2FA disabled successfully');
    }
}
