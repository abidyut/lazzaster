-- Lazzaster Gaming Platform Database Schema
-- MySQL Database for User Management, Balance Tracking, and Deposits
-- Compatible with PHP 8.3 and InfinityFree hosting
-- Created: 2025-09-17

-- Set charset and collation for proper UTF-8 support
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ============================================
-- Users Table - Core user authentication and profile data
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    auth_token VARCHAR(64) DEFAULT NULL,
    balance DECIMAL(15,2) DEFAULT 0.00,
    referral_code VARCHAR(20) UNIQUE NOT NULL,
    referred_by VARCHAR(20) DEFAULT NULL,
    status ENUM('active', 'inactive', 'suspended', 'banned') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create indexes for performance
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_auth_token ON users(auth_token);
CREATE INDEX idx_users_referral_code ON users(referral_code);

-- ============================================
-- Balance History Table - Audit trail for all balance changes
-- ============================================
CREATE TABLE IF NOT EXISTS balance_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    delta_amount DECIMAL(15,2) NOT NULL,
    previous_balance DECIMAL(15,2) NOT NULL,
    new_balance DECIMAL(15,2) NOT NULL,
    reason VARCHAR(100) NOT NULL,
    game_id VARCHAR(50) DEFAULT NULL,
    transaction_ref VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create indexes for balance history
CREATE INDEX idx_balance_history_user_id ON balance_history(user_id);
CREATE INDEX idx_balance_history_created_at ON balance_history(created_at);
CREATE INDEX idx_balance_history_reason ON balance_history(reason);

-- Composite index for efficient balance history queries by user
CREATE INDEX idx_balance_history_user_date ON balance_history(user_id, created_at DESC);

-- ============================================
-- Deposits Table - Track all deposit transactions
-- ============================================
CREATE TABLE IF NOT EXISTS deposits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    method ENUM('local', 'binance', 'crypto', 'bank', 'mobile') NOT NULL,
    transaction_id VARCHAR(100) NOT NULL UNIQUE,
    zst_amount DECIMAL(10,2) NOT NULL,
    bdt_amount DECIMAL(10,2) DEFAULT 0,
    usd_amount DECIMAL(10,2) DEFAULT 0,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    admin_notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL DEFAULT NULL,
    processed_by INT DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create indexes for deposits
CREATE INDEX idx_deposits_user_id ON deposits(user_id);
CREATE INDEX idx_deposits_status ON deposits(status);
CREATE INDEX idx_deposits_created_at ON deposits(created_at);
CREATE INDEX idx_deposits_transaction_id ON deposits(transaction_id);

-- Composite index for efficient deposit queries by user and status
CREATE INDEX idx_deposits_user_status_date ON deposits(user_id, status, created_at DESC);

-- ============================================
-- Withdrawals Table - Track withdrawal requests
-- ============================================
CREATE TABLE IF NOT EXISTS withdrawals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    method ENUM('bank', 'mobile', 'crypto') NOT NULL,
    account_details JSON NOT NULL,
    zst_amount DECIMAL(10,2) NOT NULL,
    bdt_amount DECIMAL(10,2) DEFAULT 0,
    usd_amount DECIMAL(10,2) DEFAULT 0,
    status ENUM('pending', 'approved', 'rejected', 'cancelled', 'completed') DEFAULT 'pending',
    admin_notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL DEFAULT NULL,
    processed_by INT DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create indexes for withdrawals
CREATE INDEX idx_withdrawals_user_id ON withdrawals(user_id);
CREATE INDEX idx_withdrawals_status ON withdrawals(status);
CREATE INDEX idx_withdrawals_created_at ON withdrawals(created_at);

-- Composite index for efficient withdrawal queries by user and status
CREATE INDEX idx_withdrawals_user_status_date ON withdrawals(user_id, status, created_at DESC);

-- ============================================
-- Games Table - Track game sessions and results
-- ============================================
CREATE TABLE IF NOT EXISTS game_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    game_type ENUM('dice', 'crash', 'slots', '7up7down', 'roulette') NOT NULL,
    bet_amount DECIMAL(10,2) NOT NULL,
    win_amount DECIMAL(10,2) DEFAULT 0,
    multiplier DECIMAL(10,4) DEFAULT 1.0000,
    game_data JSON DEFAULT NULL,
    seed_client VARCHAR(64) DEFAULT NULL,
    seed_server VARCHAR(64) DEFAULT NULL,
    result_hash VARCHAR(64) DEFAULT NULL,
    is_win TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create indexes for game sessions
CREATE INDEX idx_game_sessions_user_id ON game_sessions(user_id);
CREATE INDEX idx_game_sessions_game_type ON game_sessions(game_type);
CREATE INDEX idx_game_sessions_created_at ON game_sessions(created_at);
CREATE INDEX idx_game_sessions_is_win ON game_sessions(is_win);

-- ============================================
-- Referrals Table - Track referral relationships and commissions
-- ============================================
CREATE TABLE IF NOT EXISTS referral_earnings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    referrer_id INT NOT NULL,
    referred_id INT NOT NULL,
    deposit_id INT DEFAULT NULL,
    commission_rate DECIMAL(5,4) DEFAULT 0.0500,
    commission_amount DECIMAL(10,2) NOT NULL,
    commission_status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (referred_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (deposit_id) REFERENCES deposits(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create indexes for referral earnings
CREATE INDEX idx_referral_earnings_referrer_id ON referral_earnings(referrer_id);
CREATE INDEX idx_referral_earnings_referred_id ON referral_earnings(referred_id);
CREATE INDEX idx_referral_earnings_status ON referral_earnings(commission_status);

-- ============================================
-- Admin Logs Table - Track administrative actions
-- ============================================
CREATE TABLE IF NOT EXISTS admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_user_id INT DEFAULT NULL,
    details JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create indexes for admin logs
CREATE INDEX idx_admin_logs_admin_id ON admin_logs(admin_id);
CREATE INDEX idx_admin_logs_target_user_id ON admin_logs(target_user_id);
CREATE INDEX idx_admin_logs_created_at ON admin_logs(created_at);

-- ============================================
-- CHECK Constraints for Data Integrity
-- ============================================

-- Ensure user balance cannot be negative
ALTER TABLE users ADD CONSTRAINT chk_users_balance_non_negative 
    CHECK (balance >= 0);

-- Ensure deposit amounts are positive
ALTER TABLE deposits ADD CONSTRAINT chk_deposits_zst_amount_positive
    CHECK (zst_amount > 0);

ALTER TABLE deposits ADD CONSTRAINT chk_deposits_bdt_amount_non_negative
    CHECK (bdt_amount >= 0);

ALTER TABLE deposits ADD CONSTRAINT chk_deposits_usd_amount_non_negative
    CHECK (usd_amount >= 0);

-- Ensure withdrawal amounts are positive
ALTER TABLE withdrawals ADD CONSTRAINT chk_withdrawals_zst_amount_positive
    CHECK (zst_amount > 0);

ALTER TABLE withdrawals ADD CONSTRAINT chk_withdrawals_bdt_amount_non_negative
    CHECK (bdt_amount >= 0);

ALTER TABLE withdrawals ADD CONSTRAINT chk_withdrawals_usd_amount_non_negative
    CHECK (usd_amount >= 0);

-- Ensure game bet and win amounts are non-negative
ALTER TABLE game_sessions ADD CONSTRAINT chk_game_sessions_bet_amount_positive
    CHECK (bet_amount > 0);

ALTER TABLE game_sessions ADD CONSTRAINT chk_game_sessions_win_amount_non_negative
    CHECK (win_amount >= 0);

ALTER TABLE game_sessions ADD CONSTRAINT chk_game_sessions_multiplier_positive
    CHECK (multiplier > 0);

-- Ensure balance history amounts maintain consistency
ALTER TABLE balance_history ADD CONSTRAINT chk_balance_history_calculation
    CHECK (new_balance = previous_balance + delta_amount);

-- Ensure commission amounts are positive
ALTER TABLE referral_earnings ADD CONSTRAINT chk_referral_earnings_commission_positive
    CHECK (commission_amount > 0);

ALTER TABLE referral_earnings ADD CONSTRAINT chk_referral_earnings_rate_valid
    CHECK (commission_rate >= 0 AND commission_rate <= 1);

-- ============================================
-- System Settings Table - Store application configuration
-- ============================================
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('zst_to_bdt_rate', '200.00', 'number', 'ZST to BDT conversion rate'),
('zst_to_usd_rate', '0.90', 'number', 'ZST to USD conversion rate'),
('min_deposit_local', '2.00', 'number', 'Minimum deposit amount for local payments (ZST)'),
('min_deposit_binance', '5.00', 'number', 'Minimum deposit amount for Binance payments (ZST)'),
('min_withdrawal', '10.00', 'number', 'Minimum withdrawal amount (ZST)'),
('referral_commission_rate', '0.05', 'number', 'Default referral commission rate (5%)'),
('site_maintenance', 'false', 'boolean', 'Site maintenance mode'),
('registration_enabled', 'true', 'boolean', 'User registration enabled')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- ============================================
-- Additional Tables for Token Management and Admin Roles
-- ============================================

-- Admin Roles Table - Define admin permissions
CREATE TABLE IF NOT EXISTS admin_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) UNIQUE NOT NULL,
    permissions JSON DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin roles
INSERT INTO admin_roles (role_name, permissions, description) VALUES
('super_admin', '{"users":{"read":true,"write":true,"delete":true},"deposits":{"read":true,"write":true},"withdrawals":{"read":true,"write":true},"balances":{"read":true,"write":true},"settings":{"read":true,"write":true},"logs":{"read":true}}', 'Full system access'),
('admin', '{"users":{"read":true,"write":true},"deposits":{"read":true,"write":true},"withdrawals":{"read":true,"write":true},"balances":{"read":true,"write":true},"logs":{"read":true}}', 'Standard admin access'),
('moderator', '{"users":{"read":true,"write":true},"deposits":{"read":true},"withdrawals":{"read":true},"logs":{"read":true}}', 'Limited admin access')
ON DUPLICATE KEY UPDATE role_name = role_name;

-- Secure Tokens Table - Enhanced token management
CREATE TABLE IF NOT EXISTS secure_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    token_type ENUM('auth', 'reset', 'verify') DEFAULT 'auth',
    expires_at TIMESTAMP NOT NULL,
    is_revoked TINYINT(1) DEFAULT 0,
    revoked_at TIMESTAMP NULL DEFAULT NULL,
    revoked_by INT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (revoked_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create indexes for secure tokens
CREATE INDEX idx_secure_tokens_token_hash ON secure_tokens(token_hash);
CREATE INDEX idx_secure_tokens_user_id ON secure_tokens(user_id);
CREATE INDEX idx_secure_tokens_expires_at ON secure_tokens(expires_at);
CREATE INDEX idx_secure_tokens_type ON secure_tokens(token_type);

-- Add role_id column to users table for admin roles
ALTER TABLE users ADD COLUMN role_id INT DEFAULT NULL AFTER status;
ALTER TABLE users ADD FOREIGN KEY (role_id) REFERENCES admin_roles(id) ON DELETE SET NULL;
CREATE INDEX idx_users_role_id ON users(role_id);

-- ============================================
-- Chat Support Tables for Live Chat Feature
-- ============================================

-- Chat Conversations Table
CREATE TABLE IF NOT EXISTS chat_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    session_id VARCHAR(100) NOT NULL,
    status ENUM('active', 'waiting', 'closed', 'resolved') DEFAULT 'waiting',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    category ENUM('general', 'deposit', 'withdrawal', 'technical', 'complaint') DEFAULT 'general',
    assigned_admin INT DEFAULT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL DEFAULT NULL,
    rating TINYINT DEFAULT NULL,
    feedback TEXT DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_admin) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chat Messages Table
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT DEFAULT NULL,
    sender_type ENUM('user', 'admin', 'system') NOT NULL,
    message TEXT NOT NULL,
    message_type ENUM('text', 'image', 'file', 'system') DEFAULT 'text',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create indexes for chat tables
CREATE INDEX idx_chat_conversations_user_id ON chat_conversations(user_id);
CREATE INDEX idx_chat_conversations_status ON chat_conversations(status);
CREATE INDEX idx_chat_conversations_assigned_admin ON chat_conversations(assigned_admin);
CREATE INDEX idx_chat_messages_conversation_id ON chat_messages(conversation_id);
CREATE INDEX idx_chat_messages_created_at ON chat_messages(created_at);

-- ============================================
-- Views for common queries
-- ============================================

-- User summary view with balance and statistics
CREATE VIEW user_summary AS
SELECT 
    u.id,
    u.username,
    u.email,
    u.balance,
    u.referral_code,
    u.status,
    u.created_at,
    u.last_login,
    COALESCE(stats.total_deposits, 0) as total_deposits,
    COALESCE(stats.approved_deposits, 0) as approved_deposits,
    COALESCE(game_stats.total_games, 0) as total_games,
    COALESCE(game_stats.total_wins, 0) as total_wins,
    COALESCE(ref_stats.referral_count, 0) as referral_count,
    COALESCE(ref_stats.referral_earnings, 0) as referral_earnings
FROM users u
LEFT JOIN (
    SELECT 
        user_id,
        COUNT(*) as total_deposits,
        SUM(CASE WHEN status = 'approved' THEN zst_amount ELSE 0 END) as approved_deposits
    FROM deposits 
    GROUP BY user_id
) stats ON u.id = stats.user_id
LEFT JOIN (
    SELECT 
        user_id,
        COUNT(*) as total_games,
        SUM(CASE WHEN is_win = 1 THEN 1 ELSE 0 END) as total_wins
    FROM game_sessions 
    GROUP BY user_id
) game_stats ON u.id = game_stats.user_id
LEFT JOIN (
    SELECT 
        referrer_id,
        COUNT(*) as referral_count,
        SUM(CASE WHEN commission_status = 'paid' THEN commission_amount ELSE 0 END) as referral_earnings
    FROM referral_earnings 
    GROUP BY referrer_id
) ref_stats ON u.id = ref_stats.referrer_id;

-- ============================================
-- Database Setup Complete
-- ============================================
-- This MySQL schema provides a complete foundation for the Lazzaster gaming platform
-- Compatible with PHP 8.3 and InfinityFree hosting
-- 
-- Key Features:
-- - User management with authentication and role-based access
-- - Secure token management system
-- - Balance tracking with full audit trail
-- - Deposit and withdrawal processing
-- - Game session logging with provably fair seeds
-- - Referral system with commission tracking
-- - Chat support system for customer service
-- - Administrative logging and role management
-- - System configuration management
-- - Optimized with proper indexes for performance
-- 
-- Database Engine: InnoDB for ACID compliance and foreign key support
-- Character Set: utf8mb4 for full UTF-8 support including emojis
-- Currency: ZST (Lazzaster Token)
-- 1 ZST = 200 BDT ≈ $0.90 USD
--
-- FOR INFINITYFREE HOSTING SETUP:
-- 1. Create database through InfinityFree control panel
-- 2. Update config.php with your database credentials
-- 3. Run this SQL file through phpMyAdmin or MySQL command line
-- 4. Verify all tables are created successfully