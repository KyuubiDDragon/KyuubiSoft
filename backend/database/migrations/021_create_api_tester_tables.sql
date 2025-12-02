-- API Collections
CREATE TABLE IF NOT EXISTS api_collections (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    color VARCHAR(7) DEFAULT '#6366f1',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_api_collections_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API Environments (variables)
CREATE TABLE IF NOT EXISTS api_environments (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    variables JSON NULL,  -- {"baseUrl": "https://api.example.com", "token": "xxx"}
    is_active TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_api_environments_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API Requests
CREATE TABLE IF NOT EXISTS api_requests (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    collection_id VARCHAR(36) NULL,
    name VARCHAR(255) NOT NULL,
    method ENUM('GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS') DEFAULT 'GET',
    url TEXT NOT NULL,
    headers JSON NULL,
    body_type ENUM('none', 'json', 'form', 'raw', 'binary') DEFAULT 'none',
    body TEXT NULL,
    auth_type ENUM('none', 'bearer', 'basic', 'api_key') DEFAULT 'none',
    auth_config JSON NULL,
    pre_request_script TEXT NULL,
    test_script TEXT NULL,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (collection_id) REFERENCES api_collections(id) ON DELETE SET NULL,
    INDEX idx_api_requests_user (user_id),
    INDEX idx_api_requests_collection (collection_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API Request History
CREATE TABLE IF NOT EXISTS api_request_history (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    request_id VARCHAR(36) NULL,
    method VARCHAR(10) NOT NULL,
    url TEXT NOT NULL,
    request_headers JSON NULL,
    request_body TEXT NULL,
    response_status INT NULL,
    response_headers JSON NULL,
    response_body LONGTEXT NULL,
    response_time INT NULL,  -- milliseconds
    response_size INT NULL,  -- bytes
    error_message TEXT NULL,
    executed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (request_id) REFERENCES api_requests(id) ON DELETE SET NULL,
    INDEX idx_api_history_user (user_id),
    INDEX idx_api_history_request (request_id),
    INDEX idx_api_history_executed (executed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
