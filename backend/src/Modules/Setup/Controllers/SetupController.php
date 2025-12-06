<?php

declare(strict_types=1);

namespace App\Modules\Setup\Controllers;

use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use App\Core\Security\PasswordHasher;
use App\Core\Security\RbacManager;
use App\Modules\Auth\Repositories\UserRepository;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;

/**
 * Setup Controller - Handles initial system setup (first run wizard)
 */
class SetupController
{
    public function __construct(
        private readonly Connection $db,
        private readonly UserRepository $userRepository,
        private readonly PasswordHasher $passwordHasher,
        private readonly RbacManager $rbacManager
    ) {}

    /**
     * Check if setup is required (no users exist)
     */
    public function checkStatus(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userCount = $this->db->fetchOne('SELECT COUNT(*) FROM users');

        return JsonResponse::success([
            'setup_required' => $userCount == 0,
            'user_count' => (int) $userCount,
        ]);
    }

    /**
     * Complete the initial setup by creating the first admin user
     */
    public function complete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Check if setup is still needed
        $userCount = $this->db->fetchOne('SELECT COUNT(*) FROM users');
        if ($userCount > 0) {
            return JsonResponse::create(
                ['error' => 'Setup already completed'],
                400,
                'Setup has already been completed. Please log in.'
            );
        }

        $data = $request->getParsedBody() ?? [];

        // Validate required fields
        $email = trim($data['email'] ?? '');
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';
        $instanceName = trim($data['instance_name'] ?? 'KyuubiSoft');

        if (empty($email) || empty($username) || empty($password)) {
            throw new ValidationException('Email, username and password are required');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Invalid email address');
        }

        if ($password !== $confirmPassword) {
            throw new ValidationException('Passwords do not match');
        }

        // Validate password strength
        $passwordErrors = $this->passwordHasher->validateStrength($password);
        if (!empty($passwordErrors)) {
            throw new ValidationException(implode(', ', $passwordErrors));
        }

        // Create the first user
        $userId = Uuid::uuid4()->toString();
        $this->userRepository->create([
            'id' => $userId,
            'email' => $email,
            'username' => $username,
            'password_hash' => $this->passwordHasher->hash($password),
            'is_verified' => true,
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Assign the owner role
        $this->rbacManager->assignRole($userId, 'owner');

        // Store instance name in settings (if table exists)
        try {
            $this->db->executeStatement(
                "INSERT INTO settings (`key`, `value`, `type`, `group`) VALUES (?, ?, 'string', 'general')
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
                ['instance_name', $instanceName]
            );
        } catch (\Exception $e) {
            // Settings table might not exist, ignore
        }

        return JsonResponse::success([
            'message' => 'Setup completed successfully',
            'user' => [
                'id' => $userId,
                'email' => $email,
                'username' => $username,
            ],
        ]);
    }
}
