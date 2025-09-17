-- Lazzaster Gaming Platform Database Schema
-- SQLite Database for User Management, Balance Tracking, and Deposits
-- Compatible with PHP 8.3 and SQLite
-- Created: 2025-09-17

-- Enable foreign keys support
PRAGMA foreign_keys = ON;

-- ============================================
-- Users Table - Core user authentication and profile data
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    auth_token VARCHAR(64) DEFAULT NULL,
    balance DECIMAL(15,2) DEFAULT 0.00,
    referral_code VARCHAR(20) UNIQUE NOT NULL,
    referred_by VARCHAR(20) DEFAULT NULL,
    status TEXT CHECK(status IN ('active', 'inactive', 'suspended', 'banned')) DEFAULT 'active',
    role_id INTEGER DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME DEFAULT NULL
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_auth_token ON users(auth_token);
CREATE INDEX IF NOT EXISTS idx_users_referral_code ON users(referral_code);
CREATE INDEX IF NOT EXISTS idx_users_role_id ON users(role_id);

-- ============================================
-- Balance History Table - Audit trail for all balance changes
-- ============================================
CREATE TABLE IF NOT EXISTS balance_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    delta_amount DECIMAL(15,2) NOT NULL,
    previous_balance DECIMAL(15,2) NOT NULL,
    new_balance DECIMAL(15,2) NOT NULL,
    reason VARCHAR(100) NOT NULL,
    game_id VARCHAR(50) DEFAULT NULL,
    transaction_ref VARCHAR(100) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create indexes for balance history
CREATE INDEX IF NOT EXISTS idx_balance_history_user_id ON balance_history(user_id);
CREATE INDEX IF NOT EXISTS idx_balance_history_created_at ON balance_history(created_at);
CREATE INDEX IF NOT EXISTS idx_balance_history_reason ON balance_history(reason);
CREATE INDEX IF NOT EXISTS idx_balance_history_user_date ON balance_history(user_id, created_at DESC);

-- ============================================
-- Deposits Table - Track all deposit transactions
-- ============================================
CREATE TABLE IF NOT EXISTS deposits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    username VARCHAR(50) NOT NULL,
    method TEXT CHECK(method IN ('local', 'binance', 'crypto', 'bank', 'mobile')) NOT NULL,
    transaction_id VARCHAR(100) NOT NULL UNIQUE,
    zst_amount DECIMAL(10,2) NOT NULL CHECK(zst_amount > 0),
    bdt_amount DECIMAL(10,2) DEFAULT 0 CHECK(bdt_amount >= 0),
    usd_amount DECIMAL(10,2) DEFAULT 0 CHECK(usd_amount >= 0),
    status TEXT CHECK(status IN ('pending', 'approved', 'rejected', 'cancelled')) DEFAULT 'pending',
    admin_notes TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME DEFAULT NULL,
    processed_by INTEGER DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Create indexes for deposits
CREATE INDEX IF NOT EXISTS idx_deposits_user_id ON deposits(user_id);
CREATE INDEX IF NOT EXISTS idx_deposits_status ON deposits(status);
CREATE INDEX IF NOT EXISTS idx_deposits_created_at ON deposits(created_at);
CREATE INDEX IF NOT EXISTS idx_deposits_transaction_id ON deposits(transaction_id);
CREATE INDEX IF NOT EXISTS idx_deposits_user_status_date ON deposits(user_id, status, created_at DESC);

-- ============================================
-- Withdrawals Table - Track withdrawal requests
-- ============================================
CREATE TABLE IF NOT EXISTS withdrawals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    username VARCHAR(50) NOT NULL,
    method TEXT CHECK(method IN ('bank', 'mobile', 'crypto')) NOT NULL,
    account_details TEXT NOT NULL, -- JSON stored as TEXT in SQLite
    zst_amount DECIMAL(10,2) NOT NULL CHECK(zst_amount > 0),
    bdt_amount DECIMAL(10,2) DEFAULT 0 CHECK(bdt_amount >= 0),
    usd_amount DECIMAL(10,2) DEFAULT 0 CHECK(usd_amount >= 0),
    status TEXT CHECK(status IN ('pending', 'approved', 'rejected', 'cancelled', 'completed')) DEFAULT 'pending',
    admin_notes TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME DEFAULT NULL,
    processed_by INTEGER DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Create indexes for withdrawals
CREATE INDEX IF NOT EXISTS idx_withdrawals_user_id ON withdrawals(user_id);
CREATE INDEX IF NOT EXISTS idx_withdrawals_status ON withdrawals(status);
CREATE INDEX IF NOT EXISTS idx_withdrawals_created_at ON withdrawals(created_at);
CREATE INDEX IF NOT EXISTS idx_withdrawals_user_status_date ON withdrawals(user_id, status, created_at DESC);

-- ============================================
-- Games Table - Track game sessions and results
-- ============================================
CREATE TABLE IF NOT EXISTS game_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    game_type TEXT CHECK(game_type IN ('dice', 'crash', 'slots', '7up7down', 'roulette')) NOT NULL,
    bet_amount DECIMAL(10,2) NOT NULL CHECK(bet_amount > 0),
    win_amount DECIMAL(10,2) DEFAULT 0 CHECK(win_amount >= 0),
    multiplier DECIMAL(10,4) DEFAULT 1.0000 CHECK(multiplier > 0),
    game_data TEXT DEFAULT NULL, -- JSON stored as TEXT
    seed_client VARCHAR(64) DEFAULT NULL,
    seed_server VARCHAR(64) DEFAULT NULL,
    result_hash VARCHAR(64) DEFAULT NULL,
    is_win INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create indexes for game sessions
CREATE INDEX IF NOT EXISTS idx_game_sessions_user_id ON game_sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_game_sessions_game_type ON game_sessions(game_type);
CREATE INDEX IF NOT EXISTS idx_game_sessions_created_at ON game_sessions(created_at);
CREATE INDEX IF NOT EXISTS idx_game_sessions_is_win ON game_sessions(is_win);

-- ============================================
-- Referrals Table - Track referral relationships and commissions
-- ============================================
CREATE TABLE IF NOT EXISTS referral_earnings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    referrer_id INTEGER NOT NULL,
    referred_id INTEGER NOT NULL,
    deposit_id INTEGER DEFAULT NULL,
    commission_rate DECIMAL(5,4) DEFAULT 0.0500 CHECK(commission_rate >= 0 AND commission_rate <= 1),
    commission_amount DECIMAL(10,2) NOT NULL CHECK(commission_amount > 0),
    commission_status TEXT CHECK(commission_status IN ('pending', 'paid', 'cancelled')) DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    paid_at DATETIME DEFAULT NULL,
    FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (referred_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (deposit_id) REFERENCES deposits(id) ON DELETE SET NULL
);

-- Create indexes for referral earnings
CREATE INDEX IF NOT EXISTS idx_referral_earnings_referrer_id ON referral_earnings(referrer_id);
CREATE INDEX IF NOT EXISTS idx_referral_earnings_referred_id ON referral_earnings(referred_id);
CREATE INDEX IF NOT EXISTS idx_referral_earnings_status ON referral_earnings(commission_status);

-- ============================================
-- Admin Logs Table - Track administrative actions
-- ============================================
CREATE TABLE IF NOT EXISTS admin_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    admin_id INTEGER NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_user_id INTEGER DEFAULT NULL,
    details TEXT DEFAULT NULL, -- JSON stored as TEXT
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Create indexes for admin logs
CREATE INDEX IF NOT EXISTS idx_admin_logs_admin_id ON admin_logs(admin_id);
CREATE INDEX IF NOT EXISTS idx_admin_logs_target_user_id ON admin_logs(target_user_id);
CREATE INDEX IF NOT EXISTS idx_admin_logs_created_at ON admin_logs(created_at);

-- ============================================
-- System Settings Table - Store application configuration
-- ============================================
CREATE TABLE IF NOT EXISTS system_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    setting_type TEXT CHECK(setting_type IN ('string', 'number', 'boolean', 'json')) DEFAULT 'string',
    description TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insert default system settings with updated rates
INSERT OR IGNORE INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('zst_to_bdt_rate', '100.00', 'number', 'ZST to BDT conversion rate'),
('zst_to_usd_rate', '0.90', 'number', 'ZST to USD conversion rate'),
('min_deposit_local', '2.00', 'number', 'Minimum deposit amount for local payments (ZST)'),
('min_deposit_binance', '5.00', 'number', 'Minimum deposit amount for Binance payments (ZST)'),
('min_withdrawal', '5.00', 'number', 'Minimum withdrawal amount (ZST)'),
('referral_commission_rate', '0.05', 'number', 'Default referral commission rate (5%)'),
('site_maintenance', 'false', 'boolean', 'Site maintenance mode'),
('registration_enabled', 'true', 'boolean', 'User registration enabled');

-- ============================================
-- Admin Roles Table - Define admin permissions
-- ============================================
CREATE TABLE IF NOT EXISTS admin_roles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    role_name VARCHAR(50) UNIQUE NOT NULL,
    permissions TEXT DEFAULT NULL, -- JSON stored as TEXT
    description TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin roles
INSERT OR IGNORE INTO admin_roles (role_name, permissions, description) VALUES
('super_admin', '{"users":{"read":true,"write":true,"delete":true},"deposits":{"read":true,"write":true},"withdrawals":{"read":true,"write":true},"balances":{"read":true,"write":true},"settings":{"read":true,"write":true},"logs":{"read":true},"chat":{"read":true,"write":true}}', 'Full system access'),
('admin', '{"users":{"read":true,"write":true},"deposits":{"read":true,"write":true},"withdrawals":{"read":true,"write":true},"balances":{"read":true,"write":true},"logs":{"read":true},"chat":{"read":true,"write":true}}', 'Standard admin access'),
('moderator', '{"users":{"read":true,"write":true},"deposits":{"read":true},"withdrawals":{"read":true},"logs":{"read":true},"chat":{"read":true,"write":true}}', 'Limited admin access');

-- ============================================
-- Secure Tokens Table - Enhanced token management
-- ============================================
CREATE TABLE IF NOT EXISTS secure_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    token_type TEXT CHECK(token_type IN ('auth', 'reset', 'verify')) DEFAULT 'auth',
    expires_at DATETIME NOT NULL,
    is_revoked INTEGER DEFAULT 0,
    revoked_at DATETIME DEFAULT NULL,
    revoked_by INTEGER DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (revoked_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Create indexes for secure tokens
CREATE INDEX IF NOT EXISTS idx_secure_tokens_token_hash ON secure_tokens(token_hash);
CREATE INDEX IF NOT EXISTS idx_secure_tokens_user_id ON secure_tokens(user_id);
CREATE INDEX IF NOT EXISTS idx_secure_tokens_expires_at ON secure_tokens(expires_at);
CREATE INDEX IF NOT EXISTS idx_secure_tokens_type ON secure_tokens(token_type);

-- ============================================
-- Chat Support Tables for Live Chat Feature
-- ============================================

-- Chat Conversations Table
CREATE TABLE IF NOT EXISTS chat_conversations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER DEFAULT NULL,
    session_id VARCHAR(100) NOT NULL,
    status TEXT CHECK(status IN ('active', 'waiting', 'closed', 'resolved')) DEFAULT 'waiting',
    priority TEXT CHECK(priority IN ('low', 'normal', 'high', 'urgent')) DEFAULT 'normal',
    category TEXT CHECK(category IN ('general', 'deposit', 'withdrawal', 'technical', 'complaint')) DEFAULT 'general',
    assigned_admin INTEGER DEFAULT NULL,
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    closed_at DATETIME DEFAULT NULL,
    rating INTEGER DEFAULT NULL,
    feedback TEXT DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_admin) REFERENCES users(id) ON DELETE SET NULL
);

-- Chat Messages Table
CREATE TABLE IF NOT EXISTS chat_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    conversation_id INTEGER NOT NULL,
    sender_id INTEGER DEFAULT NULL,
    sender_type TEXT CHECK(sender_type IN ('user', 'admin', 'system')) NOT NULL,
    message TEXT NOT NULL,
    message_type TEXT CHECK(message_type IN ('text', 'image', 'file', 'system')) DEFAULT 'text',
    is_read INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Create indexes for chat tables
CREATE INDEX IF NOT EXISTS idx_chat_conversations_user_id ON chat_conversations(user_id);
CREATE INDEX IF NOT EXISTS idx_chat_conversations_status ON chat_conversations(status);
CREATE INDEX IF NOT EXISTS idx_chat_conversations_assigned_admin ON chat_conversations(assigned_admin);
CREATE INDEX IF NOT EXISTS idx_chat_messages_conversation_id ON chat_messages(conversation_id);
CREATE INDEX IF NOT EXISTS idx_chat_messages_created_at ON chat_messages(created_at);

-- Create trigger to update updated_at timestamp
CREATE TRIGGER IF NOT EXISTS update_users_updated_at 
    AFTER UPDATE ON users
    FOR EACH ROW 
    BEGIN 
        UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = OLD.id;
    END;

CREATE TRIGGER IF NOT EXISTS update_admin_roles_updated_at 
    AFTER UPDATE ON admin_roles
    FOR EACH ROW 
    BEGIN 
        UPDATE admin_roles SET updated_at = CURRENT_TIMESTAMP WHERE id = OLD.id;
    END;

CREATE TRIGGER IF NOT EXISTS update_system_settings_updated_at 
    AFTER UPDATE ON system_settings
    FOR EACH ROW 
    BEGIN 
        UPDATE system_settings SET updated_at = CURRENT_TIMESTAMP WHERE id = OLD.id;
    END;

-- ============================================
-- Database Setup Complete
-- ============================================
-- SQLite schema provides a complete foundation for the Lazzaster gaming platform
-- Compatible with PHP 8.3 and SQLite
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
-- Currency: ZST (Lazzaster Token)
-- Updated rates: 1 ZST = 100 BDT = $0.90 USD