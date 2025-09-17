# Lazzaster Database Documentation

## Overview
This directory contains all database-related files for the Lazzaster gaming platform. The system uses PostgreSQL as the primary database.

## Files Structure
```
database/
├── schema.sql          # Complete database schema
├── README.md          # This documentation file
└── setup.md           # Database setup instructions
```

## Database Schema Overview

### Core Tables

1. **users** - User authentication and profile data
   - Primary user information (username, email, password)
   - Authentication tokens
   - Balance tracking
   - Referral codes

2. **balance_history** - Complete audit trail of balance changes
   - Tracks all deposits, withdrawals, wins, losses
   - Provides transparency and accountability
   - Links to game sessions and transactions

3. **deposits** - Deposit transaction management
   - Supports multiple payment methods (local, Binance)
   - Status tracking (pending, approved, rejected)
   - Admin approval workflow

4. **withdrawals** - Withdrawal request management
   - Multiple withdrawal methods
   - Account details stored as JSON
   - Admin approval required

5. **game_sessions** - Game history and provably fair system
   - Tracks all game results
   - Stores client/server seeds for verification
   - Win/loss statistics

6. **referral_earnings** - Referral commission tracking
   - Links referrers to referred users
   - Commission calculations
   - Payment status tracking

7. **admin_logs** - Administrative action audit
   - Tracks all admin actions
   - IP address logging
   - Target user tracking

8. **system_settings** - Application configuration
   - Exchange rates
   - Minimum limits
   - Feature toggles

### Key Features

- **Security**: Password hashing, token-based authentication
- **Audit Trail**: Complete transaction history
- **Provably Fair**: Cryptographic game verification
- **Multi-Currency**: ZST, BDT, USD support
- **Referral System**: Commission tracking and payment
- **Admin Tools**: Comprehensive logging and controls

## Currency System

**ZST (Lazzaster Token)** is the primary platform currency:
- 1 ZST = 200 BDT (Bangladeshi Taka)
- 1 ZST ≈ $0.90 USD

## API Endpoints

The database is accessed through secure PHP API endpoints:

- `api/login.php` - User authentication
- `api/register.php` - User registration
- `api/get_balance.php` - Balance retrieval
- `api/apply_balance_delta.php` - Balance updates
- `api/deposit.php` - Deposit processing

## Security Features

1. **Password Security**: bcrypt hashing
2. **Token Authentication**: Secure session management
3. **SQL Injection Prevention**: Prepared statements
4. **CORS Protection**: Origin validation
5. **Input Sanitization**: XSS prevention
6. **Transaction Integrity**: Database transactions

## Performance Optimizations

- Strategic indexing on frequently queried columns
- Optimized views for common reporting queries
- Database connection pooling through PDO
- Efficient referential integrity constraints

## Backup and Recovery

**Important**: Always backup the database before making schema changes.

```sql
-- Create backup
pg_dump -h hostname -U username -d database_name > backup.sql

-- Restore backup
psql -h hostname -U username -d database_name < backup.sql
```

## Environment Variables

The system requires the following environment variable:
- `DATABASE_URL` - PostgreSQL connection string

Example: `postgresql://username:password@host:port/database`

## Monitoring and Maintenance

### Regular Tasks
1. Monitor database size and performance
2. Review admin logs for suspicious activity
3. Verify balance integrity through audit trails
4. Update exchange rates in system_settings
5. Clean up old session tokens

### Performance Monitoring
```sql
-- Check table sizes
SELECT schemaname,tablename,pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) as size
FROM pg_tables 
WHERE schemaname = 'public' 
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;

-- Check index usage
SELECT schemaname, tablename, attname, n_distinct, correlation 
FROM pg_stats 
WHERE schemaname = 'public' 
ORDER BY tablename, attname;
```

## Support

For database-related issues:
1. Check the error logs in `/tmp/logs/`
2. Verify environment variables are set
3. Ensure PostgreSQL service is running
4. Review connection limits and permissions

Last updated: September 17, 2025