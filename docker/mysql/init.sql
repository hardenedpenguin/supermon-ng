-- Supermon-ng Database Initialization Script
-- This script sets up the initial database structure

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS supermon CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the database
USE supermon;

-- Create users table for authentication
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    full_name VARCHAR(100),
    role ENUM('admin', 'user', 'viewer') DEFAULT 'user',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_active (is_active)
);

-- Create sessions table for session management
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    payload TEXT,
    last_activity INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_last_activity (last_activity)
);

-- Create audit_log table for security auditing
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- Create node_status table for storing node information
CREATE TABLE IF NOT EXISTS node_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    node_id VARCHAR(20) UNIQUE NOT NULL,
    callsign VARCHAR(20),
    location VARCHAR(100),
    status ENUM('online', 'offline', 'busy', 'unknown') DEFAULT 'unknown',
    last_seen TIMESTAMP NULL,
    uptime INT DEFAULT 0,
    connections INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_node_id (node_id),
    INDEX idx_status (status),
    INDEX idx_last_seen (last_seen)
);

-- Create system_metrics table for performance monitoring
CREATE TABLE IF NOT EXISTS system_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(10,4),
    metric_unit VARCHAR(20),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_metric_name (metric_name),
    INDEX idx_timestamp (timestamp)
);

-- Create API_keys table for API authentication
CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    key_name VARCHAR(100) NOT NULL,
    api_key VARCHAR(255) UNIQUE NOT NULL,
    permissions JSON,
    is_active BOOLEAN DEFAULT TRUE,
    last_used TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_api_key (api_key),
    INDEX idx_active (is_active)
);

-- Insert default admin user (password: admin123 - change in production!)
INSERT IGNORE INTO users (username, password_hash, email, full_name, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@supermon.local', 'System Administrator', 'admin');

-- Create default API key for system access
INSERT IGNORE INTO api_keys (user_id, key_name, api_key, permissions) VALUES
(1, 'System API Key', 'sk_test_51H1234567890abcdefghijklmnopqrstuvwxyz', '["read", "write"]');

-- Create indexes for better performance
CREATE INDEX idx_sessions_created_at ON sessions(created_at);
CREATE INDEX idx_audit_log_table_record ON audit_log(table_name, record_id);
CREATE INDEX idx_node_status_updated_at ON node_status(updated_at);
CREATE INDEX idx_system_metrics_name_time ON system_metrics(metric_name, timestamp);

-- Grant permissions to supermon_user
GRANT SELECT, INSERT, UPDATE, DELETE ON supermon.* TO 'supermon_user'@'%';
GRANT CREATE, DROP, INDEX, ALTER ON supermon.* TO 'supermon_user'@'%';
FLUSH PRIVILEGES;
