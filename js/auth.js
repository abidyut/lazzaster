// Shared authentication module
class AuthManager {
    constructor() {
        this.API_BASE_URL = window.location.origin;
    }

    // Check if user is logged in
    isLoggedIn() {
        return localStorage.getItem('authToken') !== null;
    }

    // Get user data from localStorage
    getUserData() {
        const userData = localStorage.getItem('userData');
        return userData ? JSON.parse(userData) : null;
    }

    // Get auth token
    getAuthToken() {
        return localStorage.getItem('authToken');
    }

    // Save auth data
    saveAuthData(token, userData) {
        localStorage.setItem('authToken', token);
        localStorage.setItem('userData', JSON.stringify(userData));
    }

    // Logout function
    logout() {
        localStorage.removeItem('authToken');
        localStorage.removeItem('userData');
        window.location.href = '/index.html'; // Use absolute path
    }

    // Get current balance from server
    async getBalance() {
        try {
            const response = await fetch(`${this.API_BASE_URL}/api/get_balance.php`, {
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + this.getAuthToken()
                }
            });
            
            const result = await response.json();
            if (result.success) {
                // Update localStorage with fresh data
                this.saveAuthData(this.getAuthToken(), result.user);
                return result.user.balance;
            } else {
                console.error('Failed to get balance:', result.message);
                return null;
            }
        } catch (error) {
            console.error('Balance fetch error:', error);
            return null;
        }
    }

    // Apply balance delta (win/loss) to current balance
    async applyBalanceDelta(deltaAmount, reason = 'game_settlement') {
        try {
            const response = await fetch(`${this.API_BASE_URL}/api/apply_balance_delta.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + this.getAuthToken()
                },
                body: JSON.stringify({
                    delta: deltaAmount,
                    reason: reason
                })
            });
            
            const result = await response.json();
            if (result.success) {
                // Update localStorage with new balance
                const userData = this.getUserData();
                userData.balance = result.new_balance;
                this.saveAuthData(this.getAuthToken(), userData);
                return result.new_balance;
            } else {
                console.error('Failed to apply balance delta:', result.message);
                return null;
            }
        } catch (error) {
            console.error('Balance delta error:', error);
            return null;
        }
    }

    // Require authentication - redirect to login if not authenticated
    requireAuth() {
        if (!this.isLoggedIn()) {
            alert('দয়া করে প্রথমে লগইন করুন');
            window.location.href = '/login.html'; // Use absolute path
            throw new Error('User not authenticated');
        }
    }
}

// Create global instance
window.authManager = new AuthManager();