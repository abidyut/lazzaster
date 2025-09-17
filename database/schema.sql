-- Lazzaster Gaming Platform Database Schema
-- PostgreSQL Database for User Management, Balance Tracking, and Deposits
-- Created: 2025-09-17

-- ============================================
-- Users Table - Core user authentication and profile data
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    auth_token VARCHAR(64) DEFAULT NULL,
    balance DECIMAL(15,2) DEFAULT 0.00,
    referral_code VARCHAR(20) UNIQUE NOT NULL,
    referred_by VARCHAR(20) DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP DEFAULT NULL
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_auth_token ON users(auth_token);
CREATE INDEX IF NOT EXISTS idx_users_referral_code ON users(referral_code);

-- ============================================
-- Balance History Table - Audit trail for all balance changes
-- ============================================
CREATE TABLE IF NOT EXISTS balance_history (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    delta_amount DECIMAL(15,2) NOT NULL,
    previous_balance DECIMAL(15,2) NOT NULL,
    new_balance DECIMAL(15,2) NOT NULL,
    reason VARCHAR(100) NOT NULL,
    game_id VARCHAR(50) DEFAULT NULL,
    transaction_ref VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for balance history
CREATE INDEX IF NOT EXISTS idx_balance_history_user_id ON balance_history(user_id);
CREATE INDEX IF NOT EXISTS idx_balance_history_created_at ON balance_history(created_at);
CREATE INDEX IF NOT EXISTS idx_balance_history_reason ON balance_history(reason);

-- Composite index for efficient balance history queries by user
CREATE INDEX IF NOT EXISTS idx_balance_history_user_date ON balance_history(user_id, created_at DESC);

-- ============================================
-- Deposits Table - Track all deposit transactions
-- ============================================
CREATE TABLE IF NOT EXISTS deposits (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    username VARCHAR(50) NOT NULL,
    method VARCHAR(20) NOT NULL, -- 'local', 'binance', 'crypto', etc.
    transaction_id VARCHAR(100) NOT NULL UNIQUE,
    zst_amount DECIMAL(10,2) NOT NULL,
    bdt_amount DECIMAL(10,2) DEFAULT 0,
    usd_amount DECIMAL(10,2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'approved', 'rejected', 'cancelled'
    admin_notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP DEFAULT NULL,
    processed_by INTEGER DEFAULT NULL REFERENCES users(id)
);

-- Create indexes for deposits
CREATE INDEX IF NOT EXISTS idx_deposits_user_id ON deposits(user_id);
CREATE INDEX IF NOT EXISTS idx_deposits_status ON deposits(status);
CREATE INDEX IF NOT EXISTS idx_deposits_created_at ON deposits(created_at);
CREATE INDEX IF NOT EXISTS idx_deposits_transaction_id ON deposits(transaction_id);

-- Composite index for efficient deposit queries by user and status
CREATE INDEX IF NOT EXISTS idx_deposits_user_status_date ON deposits(user_id, status, created_at DESC);

-- ============================================
-- Withdrawals Table - Track withdrawal requests
-- ============================================
CREATE TABLE IF NOT EXISTS withdrawals (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    username VARCHAR(50) NOT NULL,
    method VARCHAR(20) NOT NULL, -- 'bank', 'mobile', 'crypto'
    account_details JSONB NOT NULL, -- Store payment account info as JSON
    zst_amount DECIMAL(10,2) NOT NULL,
    bdt_amount DECIMAL(10,2) DEFAULT 0,
    usd_amount DECIMAL(10,2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'approved', 'rejected', 'cancelled', 'completed'
    admin_notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP DEFAULT NULL,
    processed_by INTEGER DEFAULT NULL REFERENCES users(id)
);

-- Create indexes for withdrawals
CREATE INDEX IF NOT EXISTS idx_withdrawals_user_id ON withdrawals(user_id);
CREATE INDEX IF NOT EXISTS idx_withdrawals_status ON withdrawals(status);
CREATE INDEX IF NOT EXISTS idx_withdrawals_created_at ON withdrawals(created_at);

-- Composite index for efficient withdrawal queries by user and status
CREATE INDEX IF NOT EXISTS idx_withdrawals_user_status_date ON withdrawals(user_id, status, created_at DESC);

-- ============================================
-- Games Table - Track game sessions and results
-- ============================================
CREATE TABLE IF NOT EXISTS game_sessions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    game_type VARCHAR(50) NOT NULL, -- 'dice', 'crash', 'slots', etc.
    bet_amount DECIMAL(10,2) NOT NULL,
    win_amount DECIMAL(10,2) DEFAULT 0,
    multiplier DECIMAL(10,4) DEFAULT 1.0000,
    game_data JSONB DEFAULT NULL, -- Store game-specific data as JSON
    seed_client VARCHAR(64) DEFAULT NULL,
    seed_server VARCHAR(64) DEFAULT NULL,
    result_hash VARCHAR(64) DEFAULT NULL,
    is_win BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
    id SERIAL PRIMARY KEY,
    referrer_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    referred_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    deposit_id INTEGER DEFAULT NULL REFERENCES deposits(id) ON DELETE SET NULL,
    commission_rate DECIMAL(5,4) DEFAULT 0.0500, -- 5% default
    commission_amount DECIMAL(10,2) NOT NULL,
    commission_status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'paid', 'cancelled'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP DEFAULT NULL
);

-- Create indexes for referral earnings
CREATE INDEX IF NOT EXISTS idx_referral_earnings_referrer_id ON referral_earnings(referrer_id);
CREATE INDEX IF NOT EXISTS idx_referral_earnings_referred_id ON referral_earnings(referred_id);
CREATE INDEX IF NOT EXISTS idx_referral_earnings_status ON referral_earnings(commission_status);

-- ============================================
-- Admin Logs Table - Track administrative actions
-- ============================================
CREATE TABLE IF NOT EXISTS admin_logs (
    id SERIAL PRIMARY KEY,
    admin_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    action VARCHAR(100) NOT NULL,
    target_user_id INTEGER DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
    details JSONB DEFAULT NULL,
    ip_address INET DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for admin logs
CREATE INDEX IF NOT EXISTS idx_admin_logs_admin_id ON admin_logs(admin_id);
CREATE INDEX IF NOT EXISTS idx_admin_logs_target_user_id ON admin_logs(target_user_id);
CREATE INDEX IF NOT EXISTS idx_admin_logs_created_at ON admin_logs(created_at);

-- ============================================
-- CHECK Constraints for Data Integrity
-- ============================================

-- Ensure user balance cannot be negative
ALTER TABLE users ADD CONSTRAINT chk_users_balance_non_negative 
    CHECK (balance >= 0);

-- Ensure user status has allowed values
ALTER TABLE users ADD CONSTRAINT chk_users_status_allowed
    CHECK (status IN ('active', 'inactive', 'suspended', 'banned'));

-- Ensure deposit amounts are positive
ALTER TABLE deposits ADD CONSTRAINT chk_deposits_zst_amount_positive
    CHECK (zst_amount > 0);

ALTER TABLE deposits ADD CONSTRAINT chk_deposits_bdt_amount_non_negative
    CHECK (bdt_amount >= 0);

ALTER TABLE deposits ADD CONSTRAINT chk_deposits_usd_amount_non_negative
    CHECK (usd_amount >= 0);

-- Ensure deposit status has allowed values
ALTER TABLE deposits ADD CONSTRAINT chk_deposits_status_allowed
    CHECK (status IN ('pending', 'approved', 'rejected', 'cancelled'));

-- Ensure withdrawal amounts are positive
ALTER TABLE withdrawals ADD CONSTRAINT chk_withdrawals_zst_amount_positive
    CHECK (zst_amount > 0);

ALTER TABLE withdrawals ADD CONSTRAINT chk_withdrawals_bdt_amount_non_negative
    CHECK (bdt_amount >= 0);

ALTER TABLE withdrawals ADD CONSTRAINT chk_withdrawals_usd_amount_non_negative
    CHECK (usd_amount >= 0);

-- Ensure withdrawal status has allowed values
ALTER TABLE withdrawals ADD CONSTRAINT chk_withdrawals_status_allowed
    CHECK (status IN ('pending', 'approved', 'rejected', 'cancelled', 'completed'));

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

-- Ensure referral commission status has allowed values
ALTER TABLE referral_earnings ADD CONSTRAINT chk_referral_earnings_status_allowed
    CHECK (commission_status IN ('pending', 'paid', 'cancelled'));

-- ============================================
-- System Settings Table - Store application configuration
-- ============================================
CREATE TABLE IF NOT EXISTS system_settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    setting_type VARCHAR(20) DEFAULT 'string', -- 'string', 'number', 'boolean', 'json'
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

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
ON CONFLICT (setting_key) DO NOTHING;

-- ============================================
-- Functions and Triggers
-- ============================================

-- Function to update the updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Trigger for users table
DROP TRIGGER IF EXISTS update_users_updated_at ON users;
CREATE TRIGGER update_users_updated_at
    BEFORE UPDATE ON users
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

-- Trigger for system_settings table  
DROP TRIGGER IF EXISTS update_system_settings_updated_at ON system_settings;
CREATE TRIGGER update_system_settings_updated_at
    BEFORE UPDATE ON system_settings
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

-- ============================================
-- Views for common queries
-- ============================================

-- User summary view with balance and statistics
CREATE OR REPLACE VIEW user_summary AS
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
        SUM(CASE WHEN is_win THEN 1 ELSE 0 END) as total_wins
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
-- This schema provides a complete foundation for the Lazzaster gaming platform
-- with proper indexing, relationships, and audit trails.
-- 
-- Key Features:
-- - User management with authentication
-- - Secure balance tracking with full audit trail
-- - Deposit and withdrawal processing
-- - Game session logging with provably fair seeds
-- - Referral system with commission tracking
-- - Administrative logging
-- - System configuration management
-- - Optimized with proper indexes for performance
-- 
-- Currency: ZST (Lazzaster Token)
-- 1 ZST = 200 BDT ≈ $0.90 USD