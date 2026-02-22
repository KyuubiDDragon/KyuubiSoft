<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Set up environment variables for tests
$_ENV['APP_KEY'] = 'test-app-key-for-unit-tests';
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_HOST'] = 'localhost';
$_ENV['DB_NAME'] = 'kyuubisoft_test';
$_ENV['DB_USER'] = 'test';
$_ENV['DB_PASS'] = 'test';
$_ENV['JWT_SECRET'] = 'test-jwt-secret-32-characters-long';
