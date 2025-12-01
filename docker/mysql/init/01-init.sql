-- KyuubiSoft Database Initialization
-- This file runs automatically when the MySQL container is first created

-- Set timezone
SET GLOBAL time_zone = '+00:00';

-- Create database if not exists (already created by docker-compose env vars, but just in case)
CREATE DATABASE IF NOT EXISTS kyuubisoft
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE kyuubisoft;

-- Grant privileges
GRANT ALL PRIVILEGES ON kyuubisoft.* TO 'kyuubisoft'@'%';
FLUSH PRIVILEGES;
