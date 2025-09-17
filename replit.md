# Lazzaster Gaming Platform

## Overview

Lazzaster is a Bengali-language online gaming platform focused on casino-style mini-games including Crash, Dice, and Slots. The platform targets Bangladeshi users with localized content, currency (ZST tokens), and culturally appropriate design elements. The system features user authentication, financial transactions, referral programs, and provably fair gaming mechanics.

## User Preferences

Preferred communication style: Simple, everyday language.

## System Architecture

### Frontend Architecture
- **Static HTML Pages**: Multi-page application with dedicated pages for authentication (login.html, signup.html), games (Dice/7up7down.html), user management (profile.html, deposit.html), and legal content (privacy.html, aml.html, fairness.html)
- **Mobile-First Design**: Responsive design optimized for mobile devices with max-width containers (420px-600px)
- **Bengali Localization**: Custom Kalpurush font for Bengali text with fallback to system fonts for English content
- **CSS Architecture**: Modular CSS with separate stylesheets for different page types (style_index.css, style_login.css, style_signup.css)
- **Color System**: Consistent brand colors using CSS custom properties (green theme with gold accents)

### JavaScript Architecture
- **Modular Authentication**: Centralized AuthManager class (js/auth.js) handling token-based authentication, local storage management, and API communication
- **Form Handling**: Dedicated modules for login (js/login.js) and signup (js/signup.js) with client-side validation
- **Game Logic**: Specialized game engines starting with Dice games featuring timer-based betting, provably fair mechanics, and real-time multipliers
- **API Integration**: RESTful API communication for user authentication, balance management, and transaction processing

### Backend Architecture
- **Database-First Design**: PostgreSQL database with comprehensive schema covering users, transactions, game sessions, and administrative functions
- **API Endpoints**: RESTful PHP endpoints for authentication (/api/login.php), balance retrieval (/api/get_balance.php), and transaction management
- **Security Model**: Token-based authentication with server-side session management and secure password handling
- **Transaction System**: Comprehensive audit trail through balance_history table tracking all financial movements

### Data Management
- **User System**: Complete user lifecycle management with authentication tokens, profile data, and referral tracking
- **Financial System**: Multi-currency support (local payments and Binance integration) with admin approval workflows for deposits and withdrawals
- **Game Engine**: Provably fair gaming system using client/server seeds for result verification and complete game session logging
- **Administrative Tools**: Admin logging system for tracking all administrative actions and maintaining platform integrity

## External Dependencies

### Payment Integrations
- **Binance API**: Cryptocurrency deposit and withdrawal processing
- **Local Payment Gateways**: Traditional banking integration for fiat currency transactions

### Font Resources
- **Kalpurush Font**: Bengali typography support loaded from local TTF files
- **Marqana Font**: English text styling for brand consistency

### Browser APIs
- **Web Audio API**: Sound effects for game interactions (dice rolling, win notifications)
- **LocalStorage API**: Client-side authentication token and user data persistence
- **Fetch API**: Modern HTTP client for server communication

### Development Tools
- **CSS Custom Properties**: Browser-native theming system for consistent styling
- **CSS Grid/Flexbox**: Modern layout systems for responsive design
- **ES6+ JavaScript**: Modern JavaScript features including classes, async/await, and modules

### Database Dependencies
- **PostgreSQL**: Primary database system with full ACID compliance
- **JSON Support**: Native JSON data types for flexible data storage (withdrawal account details, admin logs)